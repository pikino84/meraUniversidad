<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Borra las subidas temporales de reportes que quedaron abandonadas (INSTRUCTIVO_REPORTES.md).
 *
 * CourseReportManager las borra siempre en un finally, pero si PHP se corta a la mitad (tiempo
 * límite, reinicio del servidor) el finally no corre y los Excel quedarían en el disco. Traen
 * datos personales (CURP, RFC, NSS…), así que se revisan cada hora (app/Console/Kernel.php).
 */
class PurgeReportUploads extends Command
{
    protected $signature = 'mera:purge-report-uploads {--minutes=60 : Antigüedad mínima para borrar} {--dry-run : Solo listar}';

    protected $description = 'Borra los Excel de reportes que quedaron en la carpeta temporal (datos personales)';

    public function handle(): int
    {
        $root = (string) config('mera.reports.temp_root');
        $olderThan = now()->subMinutes(max(1, (int) $this->option('minutes')))->getTimestamp();
        $dryRun = (bool) $this->option('dry-run');
        $count = 0;

        if (File::isDirectory($root)) {
            foreach (array_merge(File::directories($root), File::files($root)) as $item) {
                $path = (string) $item;

                if (filemtime($path) >= $olderThan) {
                    continue; // puede ser una subida en curso
                }

                $this->line(($dryRun ? '[dry-run] ' : '').basename($path));

                if (! $dryRun) {
                    File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
                }

                $count++;
            }
        }

        $this->info(($dryRun ? 'Se borrarían ' : 'Borradas ')."{$count} subidas temporales de reportes.");

        return self::SUCCESS;
    }
}
