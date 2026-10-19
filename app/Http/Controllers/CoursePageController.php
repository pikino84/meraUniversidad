<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseGroupVersion;
use App\Models\Market;
use App\Models\User;
use App\Support\ProgramCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Página pública de una carpeta de curso, por versión (docs/carpetas/INSTRUCTIVO_VERSIONES.md §5).
 * El link sigue la ruta del panel (pedido del usuario 2026-10-19):
 *
 *   /<carpeta>/<año>/<versión>     p. ej. /higiene/2026/mexico, /higiene/2025/eua: el ÚNICO link que se comparte
 *   /<carpeta>/<año>               301 a la versión por defecto (México) de ese año, o a la primera visible
 *   /<carpeta>                     301 al año más reciente (mismo criterio). Links ya compartidos en producción
 *   /en/<carpeta>, /<carpeta>/en   formatos anteriores → 301 a la versión en inglés (EUA) más reciente
 *
 * - La página muestra SOLO la versión del link: sin selector de versión ni de año (quien recibe el
 *   link no navega a otras versiones).
 * - Se publica POR VERSIÓN. Apagada: el público recibe 404; los administradores ven una VISTA
 *   PREVIA con aviso.
 * - Una versión sin módulos no se muestra al público. Si es hija (Panamá → Latam), se muestra la
 *   del padre del mismo año con un aviso (§5.3).
 * - Mismo diseño que /proceso-de-manipulacion-de-alimentos (vista programs.show).
 */
class CoursePageController extends Controller
{
    /** /<carpeta>: al año más reciente. */
    public function show(Request $request, CourseGroup $courseGroup): RedirectResponse
    {
        return $this->redirectTo($request, $courseGroup, null, Market::defaultMarket());
    }

    /** /<carpeta>/<año>: a la versión por defecto de ese año. */
    public function year(Request $request, string $group, int $year): RedirectResponse
    {
        return $this->redirectTo($request, $this->group($group), $year, Market::defaultMarket());
    }

    /** /<carpeta>/<año>/<versión>: la página. */
    public function version(Request $request, string $group, int $year, string $market): View|RedirectResponse
    {
        $found = Market::findBySlug($market);
        abort_unless($found, 404);

        return $this->render($request, $this->group($group), $found, $year);
    }

    /** /en/<carpeta> y /<carpeta>/en (antes de las versiones): 301 a la versión en inglés más reciente. */
    public function legacyEnglish(Request $request, string $group): RedirectResponse
    {
        $market = Market::byCode((string) config('mera.versions.english_market'));
        abort_unless($market, 404);

        return $this->redirectTo($request, $this->group($group), null, $market, true);
    }

    /**
     * 301 a /<carpeta>/<año>/<versión>: la versión preferida del año (o del más reciente); si no la
     * hay, la primera visible en el orden del panel. $strict: solo la versión preferida.
     */
    private function redirectTo(Request $request, CourseGroup $group, ?int $year, ?Market $preferred, bool $strict = false): RedirectResponse
    {
        // Carpeta que alimenta un programa: su página pública es la del programa (link ya compartido).
        if ($programUrl = ProgramCatalog::linkedTo($group)[$preferred?->language ?? 'es'] ?? null) {
            return redirect()->to($programUrl);
        }

        $visible = $this->visibleVersions($request, $group)
            ->when($year, fn ($list) => $list->where('year', $year));

        $preferredList = $preferred ? $visible->where('market_id', $preferred->id) : collect();
        $target = $preferredList->sortByDesc('year')->first()
            ?? ($strict ? null : $visible->sortBy([['year', 'desc'], [fn ($v) => $v->market->position, 'asc']])->first());

        abort_unless($target, 404);

        return redirect()->to($target->setRelation('group', $group)->publicUrl(), 301);
    }

    /** Versiones que se pueden mostrar: publicadas (o todas, para administradores) y con módulos. */
    private function visibleVersions(Request $request, CourseGroup $group): Collection
    {
        $isAdmin = (bool) $request->user()?->hasAnyRole(User::PANEL_ROLES);

        return $group->versions()->with('market')->withCount('modules')->get()
            ->filter(fn (CourseGroupVersion $v) => $v->modules_count > 0 && ($v->is_published || $isAdmin))
            ->values();
    }

    private function render(Request $request, CourseGroup $group, Market $market, int $year): View|RedirectResponse
    {
        // Carpeta que alimenta un programa: su página pública es la del programa (link ya compartido).
        if ($programUrl = ProgramCatalog::linkedTo($group)[$market->language] ?? null) {
            return redirect()->to($programUrl);
        }

        $isAdmin = (bool) $request->user()?->hasAnyRole(User::PANEL_ROLES);
        $versions = $this->visibleVersions($request, $group);

        $version = $versions->first(fn ($v) => $v->market_id === $market->id && $v->year === $year);
        $notice = null;

        // Versión hija sin módulos (p. ej. Panamá): se muestra la del padre (Latam) con un aviso.
        if (! $version && $market->parent_id) {
            $version = $versions->first(fn ($v) => $v->market_id === $market->parent_id && $v->year === $year);
            $notice = $version ? __('program.parent_version_notice', ['version' => $version->market->name]) : null;
        }

        // Administradores: vista previa también de una versión que todavía no tiene módulos.
        if (! $version && $isAdmin && $year) {
            $version = $group->versions()->with('market')->where('market_id', $market->id)->where('year', $year)->first();
        }

        abort_unless($version, 404);
        $version->setRelation('group', $group);

        $preview = ! $version->is_published;
        abort_if($preview && ! $isAdmin, 404);

        app()->setLocale($version->language());

        $modules = $this->modules($version);
        $actions = array_values(array_filter([
            ['type' => 'evaluation', 'url' => $version->evaluation_url],
            ['type' => 'survey', 'url' => $version->survey_url],
        ], fn ($action) => filled($action['url'])));

        $title = $version->displayTitle();
        $description = filled($version->description) ? $version->description : null;

        return view('programs.show', [
            'storageKey' => $version->storageKey(),
            'legacyStorageKey' => $version->legacyStorageKey(),
            'language' => $version->language(),
            'page' => [
                // El link es de UNA versión y año: se dice cuál (p. ej. "Curso en línea · 2026 · México").
                'eyebrow' => __('program.eyebrow').' · '.$version->year.' · '.$version->market->name,
                'title' => $title,
                'lead' => $description ?? __('program.default_lead'),
                'description' => $description ?? __('program.default_description', ['title' => $title]),
            ],
            'modules' => $modules,
            'actions' => $actions,
            // Sin selector de versión ni de año: el link muestra solo su versión.
            'alternates' => [],
            'notice' => $notice,
            'preview' => $preview ? route('course-groups.versions.show', [$group, $version, 'seccion' => 'link']) : null,
            'emptyPreview' => $modules === [],
        ]);
    }

    private function group(string $slug): CourseGroup
    {
        return CourseGroup::where('slug', $slug)->firstOrFail();
    }

    /**
     * @return list<array{number:int,key:string,title:string,url:?string}>
     */
    private function modules(CourseGroupVersion $version): array
    {
        return $version->modules()
            ->ordered()
            ->get(['id', 'name', 'slug', 'path', 'position'])
            ->values()
            ->map(fn (Course $course, int $i) => [
                'number' => $i + 1,
                'key' => $course->slug,
                'title' => $course->name,
                'url' => $course->content_url,
            ])
            ->all();
    }
}
