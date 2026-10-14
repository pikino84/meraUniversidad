<?php

namespace App\Console\Commands;

use App\Models\Course;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Quita la categoría a todos los módulos (decisión del usuario, 2026-10-14): las categorías
 * quedan ocultas y los módulos se organizan solo en carpetas de curso.
 *
 * Antes de cambiar nada guarda en storage/backups un JSON con la categoría que tenía cada
 * módulo (para poder revertirlo). Las categorías en sí NO se borran. Idempotente.
 *
 *   php artisan mera:detach-categories --dry-run
 *   php artisan mera:detach-categories
 */
class DetachCategories extends Command
{
    protected $signature = 'mera:detach-categories {--dry-run : Solo mostrar qué módulos cambiarían}';

    protected $description = 'Quita la categoría a los módulos (respaldo JSON previo; no borra las categorías)';

    public function handle(): int
    {
        $courses = Course::withTrashed()
            ->with('category:id,name')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->get();

        if ($courses->isEmpty()) {
            $this->info('Ningún módulo tiene categoría. No hay nada que hacer.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Módulo', 'Categoría'], $courses->map(fn (Course $c) => [
            $c->id, mb_strimwidth($c->name, 0, 60, '…'), $c->category?->name ?? "#{$c->category_id}",
        ])->all());

        if ($this->option('dry-run')) {
            $this->info("[dry-run] Se quitaría la categoría a {$courses->count()} módulo(s).");

            return self::SUCCESS;
        }

        $backup = storage_path('backups/categorias_de_modulos_'.now()->format('Ymd_His').'.json');
        File::ensureDirectoryExists(dirname($backup));
        File::put($backup, json_encode(
            $courses->map(fn (Course $c) => ['course_id' => $c->id, 'name' => $c->name, 'category_id' => $c->category_id, 'category' => $c->category?->name])->values(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));

        if (! File::exists($backup) || File::size($backup) === 0) {
            $this->error("No se pudo escribir el respaldo [{$backup}]. No se cambió nada.");

            return self::FAILURE;
        }

        // Uno por uno (no un UPDATE masivo) para que quede en el historial y se limpie la caché de la API.
        $courses->each(fn (Course $c) => $c->update(['category_id' => null]));

        $this->info("Listo: {$courses->count()} módulo(s) sin categoría. Respaldo: {$backup}");

        return self::SUCCESS;
    }
}
