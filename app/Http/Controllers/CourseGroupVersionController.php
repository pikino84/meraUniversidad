<?php

namespace App\Http\Controllers;

use App\Models\CourseGroup;
use App\Models\CourseGroupVersion;
use App\Services\Courses\CourseGroupOrganizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Versiones de una carpeta: año + país (docs/carpetas/INSTRUCTIVO_VERSIONES.md §4).
 * Cada versión tiene sus módulos en orden, su código del catálogo, su página pública y sus
 * cortes de reporte. Panel: super admin y admin.
 */
class CourseGroupVersionController extends Controller
{
    public function __construct(private readonly CourseGroupOrganizer $organizer) {}

    /**
     * "+ Año" (con la estructura de otro año, o con las versiones elegidas) y "+ Versión" (un país
     * más en un año): mismo formulario. Las versiones nacen apagadas y sin módulos.
     */
    public function store(Request $request, CourseGroup $courseGroup): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2020,2100'],
            'copy_from' => ['nullable', 'integer', Rule::exists('course_group_versions', 'year')->where('course_group_id', $courseGroup->id)],
            'market_ids' => ['required_without:copy_from', 'array'],
            'market_ids.*' => ['integer', Rule::exists('markets', 'id')->where('is_active', true)],
        ], [
            'market_ids.required_without' => 'Elige al menos una versión (México, Latam, EUA…) o un año para copiar.',
            'copy_from.exists' => 'Ese año no existe en esta carpeta.',
        ], [
            'year' => 'año',
            'copy_from' => 'año a copiar',
            'market_ids' => 'versiones',
        ]);

        $created = $this->organizer->createYear($courseGroup, (int) $data['year'], $data['copy_from'] ?? null, $data['market_ids'] ?? []);

        if ($created === []) {
            return back()->with('error', 'Esas versiones ya existen en '.$data['year'].'.');
        }

        $labels = collect($created)->map(fn ($v) => $v->load('market')->label())->join(', ', ' y ');

        return redirect()->route('course-groups.versions.show', [$courseGroup, $created[0]])
            ->with('success', "Creada: {$labels}. Nace apagada y sin módulos.");
    }

    /** Secciones de la página de una versión (pestañas). */
    public const SECTIONS = ['modulos', 'link', 'reportes'];

    /**
     * Nivel versión (Carpeta → Año → Versión): módulos en orden, link para compartir y reportes,
     * una sección a la vez (?seccion=).
     */
    public function show(Request $request, CourseGroup $courseGroup, CourseGroupVersion $version): View
    {
        $version->setRelation('group', $courseGroup)->load(['market.parent'])->loadCount(['modules', 'reports']);
        $section = in_array($request->query('seccion'), self::SECTIONS, true) ? $request->query('seccion') : 'modulos';

        return view('course-groups.versions.show', [
            'group' => $courseGroup,
            'version' => $version,
            'section' => $section,
            'modules' => $section === 'modulos'
                ? $version->modules()->ordered()->get(['id', 'name', 'slug', 'path', 'cover_image', 'language', 'position', 'course_group_id', 'course_group_version_id'])
                : collect(),
            // Reportes de curso (docs/reportes/INSTRUCTIVO_REPORTES.md): el más reciente primero.
            'reports' => $section === 'reportes'
                ? $version->reports()->latest('cut_date')->latest('id')
                    ->get(['id', 'course_group_id', 'course_group_version_id', 'title', 'cut_date', 'summary', 'share_token', 'updated_at'])
                : collect(),
        ]);
    }

    public function edit(CourseGroup $courseGroup, CourseGroupVersion $version): View
    {
        return view('course-groups.versions.edit', ['group' => $courseGroup, 'version' => $version->load('market')]);
    }

    public function update(Request $request, CourseGroup $courseGroup, CourseGroupVersion $version): RedirectResponse
    {
        $request->merge(['catalog_code' => strtoupper(trim((string) $request->input('catalog_code'))) ?: null]);

        $data = $request->validate([
            'catalog_code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z]{3}-[A-Z]{3}-\d{3}-[A-Z]{2}-\d{2}(-[A-Z]{2,})?$/'],
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:600'],
            'evaluation_url' => ['nullable', 'url:https', 'max:500'],
            'survey_url' => ['nullable', 'url:https', 'max:500'],
        ], [
            'catalog_code.regex' => 'El código debe tener la forma del catálogo, p. ej. NRM-HIG-002-EL-26 o NRM-SEG-005-EL-26-MX.',
            '*.url' => 'Debe ser un link completo que empiece con https://',
        ], [
            'catalog_code' => 'código del catálogo',
            'title' => 'título',
            'description' => 'descripción',
            'evaluation_url' => 'link de la evaluación',
            'survey_url' => 'link de la encuesta',
        ]);

        $version->update(array_map(fn ($v) => filled($v) ? trim($v) : null, $data));

        return redirect()->route('course-groups.versions.show', [$courseGroup, $version, 'seccion' => 'link'])
            ->with('success', 'Versión '.$version->load('market')->label().' actualizada.');
    }

    /** Borra una versión VACÍA (sin módulos ni cortes). */
    public function destroy(CourseGroup $courseGroup, CourseGroupVersion $version): RedirectResponse
    {
        $label = $version->load('market')->label();

        if (! $this->organizer->deleteVersion($version)) {
            return back()->with('error', "La versión {$label} tiene módulos o cortes de reporte: muévelos o bórralos primero.");
        }

        return redirect()->route('course-groups.show', [$courseGroup, 'anio' => $version->year])->with('success', "Versión {$label} eliminada.");
    }

    /** Publica o apaga la página pública de la versión (nace apagada). */
    public function publish(Request $request, CourseGroup $courseGroup, CourseGroupVersion $version): RedirectResponse
    {
        $publish = $request->boolean('published');
        $label = $version->load('market')->label();

        if ($publish && ! $version->modules()->exists()) {
            return back()->with('error', "Agrega al menos un módulo a {$label} antes de publicar su página.");
        }

        $version->forceFill([
            'is_published' => $publish,
            'published_at' => $publish ? ($version->published_at ?? now()) : $version->published_at,
        ])->save();

        return back()->with('success', $publish
            ? "Página de {$label} publicada: ya es visible para cualquiera con el link."
            : "Página de {$label} apagada: el público ya no puede verla.");
    }

    /**
     * Guarda el nuevo orden tras arrastrar y soltar (U18). Responde JSON; las flechas ↑/↓
     * siguen funcionando sin JavaScript.
     */
    public function reorder(Request $request, CourseGroup $courseGroup, CourseGroupVersion $version): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1', 'max:500'],
            'order.*' => ['integer', 'distinct'],
        ]);

        if (! $this->organizer->reorder($version, $data['order'])) {
            return response()->json(['message' => 'La lista cambió mientras la ordenabas. Recarga la página e inténtalo de nuevo.'], 409);
        }

        return response()->json(['message' => 'Orden guardado.']);
    }
}
