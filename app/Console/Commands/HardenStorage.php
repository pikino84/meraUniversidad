<?php

namespace App\Console\Commands;

use App\Services\Courses\CourseStorage;
use Illuminate\Console\Command;

class HardenStorage extends Command
{
    protected $signature = 'mera:harden-storage {--force : Reescribe el .htaccess aunque ya exista}';

    protected $description = 'Crea el .htaccess que impide ejecutar scripts dentro de storage/app/public (cursos)';

    public function handle(CourseStorage $storage): int
    {
        $path = $storage->storageRoot().DIRECTORY_SEPARATOR.'.htaccess';

        if ($this->option('force') && file_exists($path)) {
            copy($path, $path.'.bak-'.now()->format('Ymd_His'));
            unlink($path);
        }

        $written = $storage->harden();

        $this->info($written ? "Escrito: {$path}" : "Ya estaba protegido: {$path}");

        return self::SUCCESS;
    }
}
