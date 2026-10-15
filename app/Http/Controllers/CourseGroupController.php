<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Services\Courses\CourseGroupOrganizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Carpetas de curso: Curso → Idioma (es/en) → Módulos en orden (CLAUDE.md §10).
 */
class CourseGroupController extends Controller
{
    /** Valor del selector "Mover a…" para crear una carpeta nueva al mover. */
    public const NEW_GROUP = 'new';

    public function __construct(private readonly CourseGroupOrganizer $organizer) {}

    public function index(): View
    {
        $groups = CourseGroup::query()
            ->withCount([
                'modules as es_count' => fn ($q) => $q->where('language', 'es'),
                'modules as en_count' => fn ($q) => $q->where('language', 'en'),
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('course-groups.index', [
            'groups' => $groups,
            'ungroupedCount' => Course::whereNull('course_group_id')->count(),
        ]);
    }

    public function create(): View
    {
        return view('course-groups.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $group = CourseGroup::create($this->validated($request));

        return redirect()->route('course-groups.show', $group)
            ->with('success', "Carpeta «{$group->name}» creada. Ahora agrega sus módulos.");
    }

    public function show(Request $request, CourseGroup $courseGroup): View
    {
        $language = in_array($request->query('idioma'), array_keys(CourseGroup::LANGUAGES), true)
            ? $request->query('idioma')
            : 'es';

        $modules = $courseGroup->modules()->ordered()->get(['id', 'name', 'slug', 'path', 'cover_image', 'language', 'position', 'course_group_id']);

        return view('course-groups.show', [
            'group' => $courseGroup,
            'language' => $language,
            'modules' => $modules->where('language', $language)->values(),
            'counts' => collect(CourseGroup::LANGUAGES)->map(fn ($label, $code) => $modules->where('language', $code)->count()),
            'withoutLanguage' => $modules->whereNull('language')->values(),
            // Reportes de curso (docs/reportes/INSTRUCTIVO_REPORTES.md): todos los cortes, el más reciente primero.
            'reports' => $courseGroup->reports()->latest('cut_date')->latest('id')
                ->get(['id', 'course_group_id', 'title', 'cut_date', 'summary', 'share_token', 'updated_at']),
        ]);
    }

    public function edit(CourseGroup $courseGroup): View
    {
        return view('course-groups.edit', ['group' => $courseGroup]);
    }

    public function update(Request $request, CourseGroup $courseGroup): RedirectResponse
    {
        $courseGroup->update($this->validated($request, $courseGroup));

        return redirect()->route('course-groups.show', $courseGroup)->with('success', 'Carpeta actualizada.');
    }

    public function destroy(CourseGroup $courseGroup): RedirectResponse
    {
        $released = $this->organizer->deleteGroup($courseGroup);

        return redirect()->route('course-groups.index')->with('success',
            "Carpeta «{$courseGroup->name}» eliminada. {$released} ".($released === 1 ? 'módulo regresó' : 'módulos regresaron').' a «Sin carpeta».');
    }

    public function move(Request $request, CourseGroup $courseGroup): RedirectResponse
    {
        $this->organizer->moveGroup($courseGroup, $request->input('direction') === 'up' ? 'up' : 'down');

        return back();
    }

    /**
     * Sube o baja un módulo dentro de su carpeta e idioma.
     */
    public function moveModule(Request $request, Course $course): RedirectResponse
    {
        $this->organizer->moveModule($course, $request->input('direction') === 'up' ? 'up' : 'down');

        return back();
    }

    /**
     * Guarda el nuevo orden tras arrastrar y soltar (U18). Responde JSON; las flechas ↑/↓
     * siguen funcionando sin JavaScript.
     */
    public function reorder(Request $request, CourseGroup $courseGroup): JsonResponse
    {
        $data = $request->validate([
            'language' => ['required', Rule::in(array_keys(CourseGroup::LANGUAGES))],
            'order' => ['required', 'array', 'min:1', 'max:500'],
            'order.*' => ['integer', 'distinct'],
        ]);

        if (! $this->organizer->reorder($courseGroup, $data['language'], $data['order'])) {
            return response()->json(['message' => 'La lista cambió mientras la ordenabas. Recarga la página e inténtalo de nuevo.'], 409);
        }

        return response()->json(['message' => 'Orden guardado.']);
    }

    /**
     * Quita un módulo de su carpeta (regresa a "Sin carpeta"). No borra el módulo.
     */
    public function removeModule(Course $course): RedirectResponse
    {
        $this->organizer->moveModules([$course->id], null, null);

        return back()->with('success', "«{$course->name}» regresó a «Sin carpeta».");
    }

    /**
     * Selección múltiple → "Mover a…" (carpeta + idioma, o "Sin carpeta").
     */
    public function bulkMove(Request $request): RedirectResponse
    {
        // "+ Nueva carpeta…" (course_group_id = new): se crea la carpeta y se mueven los módulos en
        // un solo paso, sin salir de la lista filtrada (pedido del usuario 2026-10-15).
        $creating = $request->input('course_group_id') === self::NEW_GROUP;

        $data = $request->validate([
            'course_ids' => ['required', 'array', 'min:1', 'max:500'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'course_group_id' => $creating ? ['required'] : ['nullable', 'integer', 'exists:course_groups,id'],
            'new_group_name' => $creating
                ? ['required', 'string', 'max:150', Rule::unique('course_groups', 'name')]
                : ['nullable'],
            'language' => ['nullable', 'required_with:course_group_id', Rule::in(array_keys(CourseGroup::LANGUAGES))],
        ], [
            'course_ids.required' => 'Selecciona al menos un módulo.',
            'language.required_with' => 'Elige el idioma (Español o Inglés).',
            'new_group_name.required' => 'Escribe el nombre de la carpeta nueva.',
            'new_group_name.unique' => 'Ya existe una carpeta con ese nombre: elígela en la lista.',
        ], [
            'course_group_id' => 'carpeta',
            'new_group_name' => 'nombre de la carpeta',
            'language' => 'idioma',
        ]);

        $group = null;
        $moved = DB::transaction(function () use ($creating, $data, &$group) {
            $group = $creating
                ? CourseGroup::create(['name' => trim($data['new_group_name'])])
                : (isset($data['course_group_id']) ? CourseGroup::find($data['course_group_id']) : null);

            return $this->organizer->moveModules($data['course_ids'], $group?->id, $group ? $data['language'] : null);
        });

        $destination = $group
            ? '«'.$group->name.'» · '.CourseGroup::languageLabel($data['language'])
            : '«Sin carpeta»';
        $message = "{$moved} ".($moved === 1 ? 'módulo movido' : 'módulos movidos')." a {$destination}.";

        return back()->with('success', $creating ? "Carpeta «{$group->name}» creada. {$message}" : $message);
    }

    /**
     * Publica o apaga la página pública de la carpeta (nace apagada).
     */
    public function publish(Request $request, CourseGroup $courseGroup): RedirectResponse
    {
        $publish = $request->boolean('published');

        if ($publish && ! $courseGroup->modules()->whereNotNull('language')->exists()) {
            return back()->with('error', 'Agrega al menos un módulo en Español o Inglés antes de publicar la página.');
        }

        $courseGroup->forceFill([
            'is_published' => $publish,
            'published_at' => $publish ? ($courseGroup->published_at ?? now()) : $courseGroup->published_at,
        ])->save();

        return back()->with('success', $publish
            ? "Página de «{$courseGroup->name}» publicada: ya es visible para cualquiera con el link."
            : "Página de «{$courseGroup->name}» apagada: el público ya no puede verla.");
    }

    private function validated(Request $request, ?CourseGroup $group = null): array
    {
        $slug = Str::lower(trim((string) $request->input('slug')));
        $request->merge(['slug' => $slug === '' ? null : $slug]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('course_groups', 'name')->ignore($group?->id)],
            'slug' => [
                $group ? 'required' : 'nullable', 'string', 'max:120',
                'regex:/^'.CourseGroup::SLUG_PATTERN.'$/',
                Rule::unique('course_groups', 'slug')->ignore($group?->id),
                function ($attribute, $value, $fail) {
                    if (filled($value) && in_array($value, CourseGroup::reservedSlugs(), true)) {
                        $fail('Ese link está reservado por el sistema. Elige otro.');
                    }
                },
            ],
            'name_en' => ['nullable', 'string', 'max:150'],
            'description_es' => ['nullable', 'string', 'max:600'],
            'description_en' => ['nullable', 'string', 'max:600'],
            'evaluation_url_es' => ['nullable', 'url:https', 'max:500'],
            'evaluation_url_en' => ['nullable', 'url:https', 'max:500'],
            'survey_url_es' => ['nullable', 'url:https', 'max:500'],
            'survey_url_en' => ['nullable', 'url:https', 'max:500'],
        ], [
            'name.unique' => 'Ya existe una carpeta con ese nombre.',
            'slug.unique' => 'Ya hay otra carpeta con ese link.',
            'slug.regex' => 'El link solo puede llevar minúsculas, números y guiones (sin espacios ni acentos).',
            '*.url' => 'Debe ser un link completo que empiece con https://',
        ], [
            'name' => 'nombre',
            'slug' => 'link',
            'name_en' => 'nombre en inglés',
            'description_es' => 'descripción en español',
            'description_en' => 'descripción en inglés',
            'evaluation_url_es' => 'evaluación (español)',
            'evaluation_url_en' => 'evaluación (inglés)',
            'survey_url_es' => 'encuesta (español)',
            'survey_url_en' => 'encuesta (inglés)',
        ]);

        $clean = fn ($value) => filled($value) ? trim($value) : null;

        $attributes = ['name' => trim($data['name'])];

        foreach (['name_en', 'description_es', 'description_en', 'evaluation_url_es', 'evaluation_url_en', 'survey_url_es', 'survey_url_en'] as $field) {
            $attributes[$field] = $clean($data[$field] ?? null);
        }

        // En alta, si no se escribe un link, se genera del nombre (evitando los reservados).
        if (filled($data['slug'] ?? null)) {
            $attributes['slug'] = $data['slug'];
        }

        return $attributes;
    }
}
