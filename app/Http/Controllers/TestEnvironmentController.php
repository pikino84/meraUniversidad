<?php

namespace App\Http\Controllers;

use App\Support\TestEnvironment;
use Illuminate\Http\RedirectResponse;

/**
 * "Apagar todo lo compartido" del cintillo de pruebas (App\Support\TestEnvironment).
 * En producción la ruta responde 404.
 */
class TestEnvironmentController extends Controller
{
    public function unshare(): RedirectResponse
    {
        abort_unless(TestEnvironment::active(), 404);

        $counts = TestEnvironment::unshareAll();

        activity('versiones')
            ->causedBy(auth()->user())
            ->withProperties($counts)
            ->log('Entorno de pruebas: se apagó todo lo compartido');

        $total = $counts['pages'] + $counts['reports'];

        return back()->with('success', $total === 0
            ? 'No había nada compartido.'
            : "Listo: {$counts['pages']} ".($counts['pages'] === 1 ? 'página apagada' : 'páginas apagadas')
                ." y {$counts['reports']} ".($counts['reports'] === 1 ? 'link de reporte desactivado' : 'links de reporte desactivados')
                .'. Quien tenga esos links ya no podrá verlos.');
    }
}
