<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\User;
use App\Support\ProgramCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página pública de una carpeta de curso: /<slug> (español) y /en/<slug> (inglés).
 *
 * - Apagada hasta que se publique: el público recibe 404; los administradores ven una
 *   VISTA PREVIA con aviso (para revisarla antes de publicar).
 * - Un idioma sin módulos no se muestra al público (404) ni aparece en el selector.
 * - Mismo diseño que /proceso-de-manipulacion-de-alimentos (vista programs.show).
 */
class CoursePageController extends Controller
{
    /** /en/<slug>: el idioma va primero en la URL (pedido del usuario 2026-10-15). */
    public function showLanguage(Request $request, string $language, CourseGroup $courseGroup): View|RedirectResponse
    {
        return $this->show($request, $courseGroup, $language);
    }

    /** /<slug>/en (formato anterior): 301 al nuevo, para no romper links ya compartidos. */
    public function legacyLanguage(CourseGroup $courseGroup, string $language): RedirectResponse
    {
        return redirect()->to($courseGroup->publicUrl($language), 301);
    }

    public function show(Request $request, CourseGroup $courseGroup, string $language = 'es'): View|RedirectResponse
    {
        abort_unless(array_key_exists($language, CourseGroup::LANGUAGES), 404);

        // Carpeta que alimenta un programa: su página pública es la del programa (link ya compartido).
        if ($programUrl = ProgramCatalog::linkedTo($courseGroup)[$language] ?? null) {
            return redirect()->to($programUrl);
        }

        $isAdmin = (bool) $request->user()?->hasAnyRole(User::PANEL_ROLES);
        $preview = ! $courseGroup->is_published;

        abort_if($preview && ! $isAdmin, 404);

        $modules = $this->modules($courseGroup, $language);

        abort_if($modules === [] && ! $isAdmin, 404);

        app()->setLocale($language);

        $alternates = [];
        foreach (array_keys(CourseGroup::LANGUAGES) as $code) {
            if ($code === $language || $this->modules($courseGroup, $code) !== []) {
                $alternates[$code] = $courseGroup->publicUrl($code);
            }
        }

        $actions = array_values(array_filter([
            ['type' => 'evaluation', 'url' => $courseGroup->actionUrl('evaluation', $language)],
            ['type' => 'survey', 'url' => $courseGroup->actionUrl('survey', $language)],
        ], fn ($action) => filled($action['url'])));

        $title = $courseGroup->titleFor($language);
        $description = $courseGroup->descriptionFor($language);

        return view('programs.show', [
            'storageKey' => "carpeta:{$courseGroup->slug}:{$language}",
            'language' => $language,
            'page' => [
                'eyebrow' => __('program.eyebrow'),
                'title' => $title,
                'lead' => $description ?? __('program.default_lead'),
                'description' => $description ?? __('program.default_description', ['title' => $title]),
            ],
            'modules' => $modules,
            'actions' => $actions,
            'alternates' => $alternates,
            'preview' => $preview ? $courseGroup : null,
            'emptyPreview' => $modules === [],
        ]);
    }

    /**
     * @return list<array{number:int,key:string,title:string,url:?string}>
     */
    private function modules(CourseGroup $group, string $language): array
    {
        return $group->modules()
            ->where('language', $language)
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
