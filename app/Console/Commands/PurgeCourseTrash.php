<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Elimina definitivamente lo que lleva más de N días en la papelera de cursos y
 * las extracciones temporales abandonadas (subidas interrumpidas).
 */
class PurgeCourseTrash extends Command
{
    protected $signature = 'mera:purge-course-trash {--days= : Días de retención (por defecto config mera.courses.trash_retention_days)} {--dry-run : Solo listar}';

    protected $description = 'Purga la papelera de cursos y las carpetas temporales de subida viejas';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('mera.courses.trash_retention_days'));
        $dryRun = (bool) $this->option('dry-run');

        $purged = $this->purge(config('mera.courses.trash_root'), now()->subDays($days)->getTimestamp(), $dryRun);
        $purged += $this->purge(config('mera.courses.temp_root'), now()->subDay()->getTimestamp(), $dryRun);

        $this->info(($dryRun ? 'Se eliminarían ' : 'Eliminados ')."{$purged} elementos.");

        return self::SUCCESS;
    }

    private function purge(string $root, int $olderThan, bool $dryRun): int
    {
        if (! File::isDirectory($root)) {
            return 0;
        }

        $count = 0;

        foreach (array_merge(File::directories($root), File::files($root)) as $item) {
            $path = (string) $item;

            if (filemtime($path) >= $olderThan) {
                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '').$path);

            if (! $dryRun) {
                File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
            }

            $count++;
        }

        return $count;
    }
}
