<?php

namespace App\Console\Commands;

use App\Services\Reports\CourseCatalogImporter;
use App\Services\Reports\ReportFormatException;
use Illuminate\Console\Command;

/**
 * Carga el catálogo de cursos (y, opcional, los códigos alternos) desde la terminal. Lo mismo se
 * hace en el panel: Catálogo de cursos → Cargar catálogo (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §6).
 *
 *   php artisan mera:catalog-import storage/app/reportes/referencia/Catalogo_MERA_v12.xlsx --dry-run
 *   php artisan mera:catalog-import <xlsx> --aliases=docs/reportes/equivalencias_codigos.csv
 */
class ImportCourseCatalog extends Command
{
    protected $signature = 'mera:catalog-import
        {file : Excel del catálogo (Catalogo_MERA_v*.xlsx)}
        {--aliases= : CSV de equivalencias de códigos (codigo_plataforma, codigo_catalogo_v12)}
        {--dry-run : Solo mostrar lo que haría}';

    protected $description = 'Carga o actualiza el catálogo de cursos (no borra cursos ni cambia lo editado en el panel)';

    public function handle(CourseCatalogImporter $importer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $importer->import((string) $this->argument('file'), $dryRun);
        } catch (ReportFormatException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Nuevos: %d · Actualizados: %d · Sin cambios: %d', count($result['created']), count($result['updated']), $result['unchanged']));
        $this->listing('Nuevos', $result['created']);
        $this->listing('Actualizados', $result['updated']);
        $this->listing('Filas que no se pudieron leer', $result['skipped'], 'warn');
        $this->listing('En la base pero no en este Excel (no se borran)', $result['missing'], 'warn');
        $this->listing('Sin columna «Alcance»: se tomó de «Nivel» (Todo MERA = General)', $result['scope_from_level'], 'warn');
        $this->listing('Alcance distinto entre hojas (ganó la primera)', $result['scope_conflicts'], 'warn');

        if ($this->option('aliases')) {
            if (! is_file($this->option('aliases'))) {
                $this->error('No existe el archivo de equivalencias.');

                return self::FAILURE;
            }

            if ($dryRun) {
                $this->line('[dry-run] Los códigos alternos se cargan después del catálogo: corre sin --dry-run para verlos.');
            } else {
                $aliases = $importer->importAliases($this->option('aliases'));
                $this->info("Códigos alternos agregados: {$aliases['added']}");
                $this->listing('Equivalencias sin curso en el catálogo', $aliases['unmatched'], 'warn');
            }
        }

        if ($dryRun) {
            $this->line('[dry-run] No se escribió nada.');
        }

        return self::SUCCESS;
    }

    private function listing(string $title, array $items, string $style = 'line'): void
    {
        if ($items === []) {
            return;
        }

        $this->{$style}("{$title} (".count($items).'):');
        foreach ($items as $item) {
            $this->line("  - {$item}");
        }
    }
}
