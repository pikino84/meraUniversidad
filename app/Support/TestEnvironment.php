<?php

namespace App\Support;

use App\Models\CourseGroup;
use App\Models\CourseGroupVersion;
use App\Models\CourseReport;
use App\Models\ProgressReportShare;
use Illuminate\Support\Facades\DB;

/**
 * Entorno de pruebas (QA en university.meracorporation.com y local): todo lo que no es producción.
 * Capacitación llegó a subir y compartir material real en QA (2026-10-19), así que cada página
 * lleva un cintillo (partials/test-environment) y el panel permite apagar de un golpe todo lo
 * compartido: páginas públicas de versión, links de reportes por curso y del seguimiento anual.
 * Quien tenga esos links verá 404.
 */
final class TestEnvironment
{
    public static function active(): bool
    {
        return ! app()->isProduction();
    }

    /**
     * Lo que hoy se ve sin sesión. Las páginas de programa (config/mera.php) no cuentan: no se
     * publican desde el panel.
     *
     * @return array{pages: int, reports: int}
     */
    public static function sharedCounts(): array
    {
        return [
            'pages' => CourseGroupVersion::where('is_published', true)->count(),
            // Links de reportes por curso y del seguimiento anual (§7.2.7).
            'reports' => CourseReport::whereNotNull('share_token')->count() + ProgressReportShare::count(),
        ];
    }

    /**
     * Apaga todas las páginas de versión y revoca todos los links de reportes.
     *
     * @return array{pages: int, reports: int}
     */
    public static function unshareAll(): array
    {
        return DB::transaction(function () {
            $counts = [
                'pages' => CourseGroupVersion::where('is_published', true)->update(['is_published' => false]),
                'reports' => CourseReport::whereNotNull('share_token')->update(['share_token' => null, 'shared_at' => null])
                    + ProgressReportShare::query()->delete(),
            ];

            // Columna anterior de la carpeta (respaldo, ya no publica nada): se apaga para que
            // mera:versions-from-languages no vuelva a publicar al crear versiones.
            CourseGroup::where('is_published', true)->update(['is_published' => false]);

            return $counts;
        });
    }
}
