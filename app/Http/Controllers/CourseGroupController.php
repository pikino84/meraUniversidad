<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\Market;
use App\Services\Courses\CourseGroupOrganizer;
use App\Support\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Carpetas de curso: Curso → Año → Versión por país → Módulos en orden (CLAUDE.md §10 y
 * docs/carpetas/INSTRUCTIVO_VERSIONES.md). Las versiones se administran en CourseGroupVersionController.
 */
class CourseGroupController extends Controller
{
    /** Valor del selector "Mover a…" para crear una carpeta nueva al mover. */
    public const NEW_GROUP = 'new';

    public function __construct(private readonly CourseGroupOrganizer $organizer) {}

    /** Carpetas por página (pedido del usuario, 2026-10-22: paginado y buscador). */
    public const PER_PAGE = 10;

    public function index(Request $request): View
    {
        $search = Str::limit(trim((string) $request->query('q')), 100, '');

        $groups = CourseGroup::query()
            ->with(['versions' => fn ($q) => $q->with('market')->withCount('modules')->ordered()])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => Like::contains($q, ['name', 'slug'], $search)
                ->orWhereHas('versions', fn ($v) => Like::contains($v, ['catalog_code'], $search))))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->onEachSide(1)
            ->withQueryString();

        return view('course-groups.index', [
            'groups' => $groups,
            'search' => $search,
            'total' => CourseGroup::count(),
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

    /**
     * Nivel carpeta: años y, del año elegido, sus versiones como tarjetas (pedido del usuario
     * 2026-10-19: Carpeta → Año → Versión → Módulos). Los módulos, el link y los reportes viven en
     * la página de cada versión (CourseGroupVersionController::show).
     */
    public function show(Request $request, CourseGroup $courseGroup): View|RedirectResponse
    {
        $versions = $courseGroup->versions()->with('market')->withCount(['modules', 'reports'])->ordered()->get();

        // Links anteriores (?version=N): la página de la versión.
        if ($request->filled('version') && $version = $versions->firstWhere('id', (int) $request->query('version'))) {
            return redirect()->route('course-groups.versions.show', [$courseGroup, $version]);
        }

        $years = $versions->groupBy('year');
        $year = $years->has((int) $request->query('anio')) ? (int) $request->query('anio') : $years->keys()->first();

        return view('course-groups.show', [
            'group' => $courseGroup,
            'versions' => $versions,
            'years' => $years,
            'year' => $year,
            // Módulos de la carpeta que todavía no tienen versión (antes de mera:versions-from-languages).
            'withoutVersion' => $courseGroup->modules()->whereNull('course_group_version_id')->count(),
            'reportsWithoutVersion' => $courseGroup->reports()->whereNull('course_group_version_id')->latest('cut_date')->get(['id', 'course_group_id', 'title', 'cut_date']),
            'markets' => Market::cached()->where('is_active', true)->values(),
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
     * Sube o baja un módulo dentro de su versión.
     */
    public function moveModule(Request $request, Course $course): RedirectResponse
    {
        $this->organizer->moveModule($course, $request->input('direction') === 'up' ? 'up' : 'down');

        return back();
    }

    /**
     * Quita un módulo de su carpeta (regresa a "Sin carpeta"). No borra el módulo.
     */
    public function removeModule(Course $course): RedirectResponse
    {
        $this->organizer->moveModules([$course->id], null);

        return back()->with('success', "«{$course->name}» regresó a «Sin carpeta».");
    }

    /**
     * Selección múltiple → "Mover a…": carpeta + año + versión (la versión se crea si no existe),
     * o "Sin carpeta".
     */
    public function bulkMove(Request $request): RedirectResponse
    {
        // "+ Nueva carpeta…" (course_group_id = new): se crea la carpeta y se mueven los módulos en
        // un solo paso, sin salir de la lista filtrada (pedido del usuario 2026-10-15).
        $creating = $request->input('course_group_id') === self::NEW_GROUP;
        $grouping = $creating || filled($request->input('course_group_id'));

        $data = $request->validate([
            'course_ids' => ['required', 'array', 'min:1', 'max:500'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'course_group_id' => $creating ? ['required'] : ['nullable', 'integer', 'exists:course_groups,id'],
            'new_group_name' => $creating
                ? ['required', 'string', 'max:150', Rule::unique('course_groups', 'name')]
                : ['nullable'],
            'year' => $grouping ? ['required', 'integer', 'between:2020,2100'] : ['nullable'],
            'market_id' => $grouping ? ['required', 'integer', Rule::exists('markets', 'id')->where('is_active', true)] : ['nullable'],
        ], [
            'course_ids.required' => 'Selecciona al menos un módulo.',
            'year.required' => 'Escribe el año.',
            'market_id.required' => 'Elige la versión (México, Latam, EUA…).',
            'new_group_name.required' => 'Escribe el nombre de la carpeta nueva.',
            'new_group_name.unique' => 'Ya existe una carpeta con ese nombre: elígela en la lista.',
        ], [
            'course_group_id' => 'carpeta',
            'new_group_name' => 'nombre de la carpeta',
            'year' => 'año',
            'market_id' => 'versión',
        ]);

        $version = null;
        $moved = DB::transaction(function () use ($creating, $grouping, $data, &$version) {
            if ($grouping) {
                $group = $creating
                    ? CourseGroup::create(['name' => trim($data['new_group_name'])])
                    : CourseGroup::findOrFail($data['course_group_id']);
                $version = $this->organizer->version($group, (int) $data['year'], Market::findOrFail($data['market_id']));
            }

            return $this->organizer->moveModules($data['course_ids'], $version);
        });

        $destination = $version ? '«'.$version->group->name.'» · '.$version->label() : '«Sin carpeta»';
        $message = "{$moved} ".($moved === 1 ? 'módulo movido' : 'módulos movidos')." a {$destination}.";

        return back()->with('success', $creating ? "Carpeta «{$version->group->name}» creada. {$message}" : $message);
    }

    /**
     * Nombre y link de la carpeta. La descripción, la evaluación, la encuesta y la publicación son
     * de cada versión (CourseGroupVersionController); las columnas viejas de la carpeta
     * (name_en, description_*, *_url_*, is_published) se conservan solo como respaldo.
     */
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
                        $fail('Ese link está reservado por el sistema (o es el de una versión). Elige otro.');
                    }
                },
            ],
        ], [
            'name.unique' => 'Ya existe una carpeta con ese nombre.',
            'slug.unique' => 'Ya hay otra carpeta con ese link.',
            'slug.regex' => 'El link solo puede llevar minúsculas, números y guiones (sin espacios ni acentos).',
        ], [
            'name' => 'nombre',
            'slug' => 'link',
        ]);

        $attributes = ['name' => trim($data['name'])];

        // En alta, si no se escribe un link, se genera del nombre (evitando los reservados).
        if (filled($data['slug'] ?? null)) {
            $attributes['slug'] = $data['slug'];
        }

        return $attributes;
    }
}
