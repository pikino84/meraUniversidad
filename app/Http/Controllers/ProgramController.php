<?php

namespace App\Http\Controllers;

use App\Support\ProgramCatalog;
use Illuminate\View\View;

/**
 * Páginas públicas de programa multi-idioma (config('mera.programs')).
 * Cada idioma tiene su propia URL; el acceso es público, igual que /cursos.
 */
class ProgramController extends Controller
{
    public function show(string $program): View
    {
        $resolved = ProgramCatalog::resolve($program);

        abort_unless($resolved !== null, 404);

        // Textos de la página (lang/<idioma>/program.php) en el idioma de esta URL.
        app()->setLocale($resolved['language']);

        return view('programs.show', [
            'storageKey' => $resolved['page']['slug'],
            'language' => $resolved['language'],
            'page' => $resolved['page'],
            'modules' => $resolved['modules'],
            'actions' => $resolved['actions'],
            'alternates' => $resolved['alternates'],
        ]);
    }
}
