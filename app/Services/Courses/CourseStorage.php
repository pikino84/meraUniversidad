<?php

namespace App\Services\Courses;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Punto único de acceso al sistema de archivos de los cursos.
 *
 * Toda ruta que se vaya a crear, mover o borrar pasa por resolve(), que garantiza
 * que queda DENTRO de la carpeta de cursos (nunca la carpeta raíz ni fuera de ella).
 */
class CourseStorage
{
    public const HTACCESS_MARKER = '# mera:harden-storage';

    public function storageRoot(): string
    {
        return rtrim(config('mera.courses.storage_root'), '/\\');
    }

    public function directory(): string
    {
        return trim(config('mera.courses.directory'), '/\\');
    }

    public function coursesRoot(): string
    {
        return $this->storageRoot().DIRECTORY_SEPARATOR.$this->directory();
    }

    /**
     * Convierte una ruta relativa de BD ("cursos/curso-AbC") en absoluta, validando
     * que esté dentro de la carpeta de cursos y que no sea la carpeta misma.
     */
    public function resolve(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $prefix = $this->directory().'/';

        if (! str_starts_with($relativePath, $prefix)) {
            throw new RuntimeException("Ruta de curso fuera de la carpeta de cursos: {$relativePath}");
        }

        $inner = substr($relativePath, strlen($prefix));
        $segments = array_filter(explode('/', $inner), fn ($s) => $s !== '');

        if ($segments === [] || in_array('..', $segments, true) || in_array('.', $segments, true)) {
            throw new RuntimeException("Ruta de curso no válida: {$relativePath}");
        }

        return $this->coursesRoot().DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);
    }

    public function relative(string $name): string
    {
        return $this->directory().'/'.$name;
    }

    /**
     * Nombre de carpeta inmutable para un curso nuevo (independiente del nombre/slug,
     * así renombrar un curso nunca mueve archivos).
     */
    public function newFolderName(): string
    {
        do {
            $name = 'curso-'.Str::random(10);
        } while (File::exists($this->coursesRoot().DIRECTORY_SEPARATOR.$name));

        return $name;
    }

    public function ensureCoursesRoot(): void
    {
        File::ensureDirectoryExists($this->coursesRoot(), 0755);
        $this->harden();
    }

    /**
     * Escribe un .htaccess en la raíz del disco público que impide ejecutar scripts
     * de servidor aunque llegaran a subirse. Es idempotente.
     */
    public function harden(): bool
    {
        $path = $this->storageRoot().DIRECTORY_SEPARATOR.'.htaccess';

        if (File::exists($path) && str_contains(File::get($path), self::HTACCESS_MARKER)) {
            return false;
        }

        File::ensureDirectoryExists($this->storageRoot(), 0755);
        File::put($path, $this->htaccessContents());

        return true;
    }

    public function htaccessContents(): string
    {
        $marker = self::HTACCESS_MARKER;

        return <<<HTACCESS
{$marker}
# Generado automáticamente por Mera Universidad. Bloquea la ejecución de scripts
# dentro de storage/app/public (servido como /storage). Regenerar con:
#   php artisan mera:harden-storage

# Desactiva el intérprete PHP (mod_php). En PHP-FPM/LSAPI lo cubre el bloque FilesMatch.
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>

RemoveHandler .php .phtml .phar .pht .phps .php3 .php4 .php5 .php7 .php8 .cgi .pl .py .sh
RemoveType .php .phtml .phar .pht .phps .php3 .php4 .php5 .php7 .php8

<FilesMatch "(?i)\.(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp)$">
    Require all denied
</FilesMatch>

<FilesMatch "^\.">
    Require all denied
</FilesMatch>

<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
</IfModule>

HTACCESS;
    }

    /**
     * Mueve un directorio (rename atómico si es el mismo disco; copia+borrado si no).
     */
    public function moveDirectory(string $from, string $to): void
    {
        File::ensureDirectoryExists(dirname($to), 0755);

        if (@rename($from, $to)) {
            return;
        }

        if (! File::copyDirectory($from, $to)) {
            throw new RuntimeException("No se pudo mover el directorio {$from} → {$to}");
        }

        File::deleteDirectory($from);
    }

    /**
     * En vez de borrar definitivamente, manda el curso a la papelera (se purga con
     * `php artisan mera:purge-course-trash`).
     */
    public function trash(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        $absolute = $this->resolve($relativePath);

        if (! File::exists($absolute)) {
            return;
        }

        $trashRoot = rtrim(config('mera.courses.trash_root'), '/\\');
        $target = $trashRoot.DIRECTORY_SEPARATOR.now()->format('Ymd_His').'_'.Str::random(4).'_'.basename($absolute);

        File::ensureDirectoryExists($trashRoot, 0755);

        if (File::isDirectory($absolute)) {
            $this->moveDirectory($absolute, $target);
        } else {
            File::move($absolute, $target);
        }
    }

    public function deleteQuietly(?string $relativePath): void
    {
        if (! $relativePath) {
            return;
        }

        try {
            $absolute = $this->resolve($relativePath);
        } catch (RuntimeException) {
            return;
        }

        File::isDirectory($absolute) ? File::deleteDirectory($absolute) : File::delete($absolute);
    }

    /**
     * Límite real de subida: el menor entre la configuración de la app y php.ini.
     */
    public function effectiveMaxUploadBytes(): int
    {
        $app = (int) config('mera.courses.max_zip_kb') * 1024;
        $ini = min(
            self::iniBytes(ini_get('upload_max_filesize')),
            self::iniBytes(ini_get('post_max_size'))
        );

        return $ini > 0 ? min($app, $ini) : $app;
    }

    private static function iniBytes(string|false $value): int
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '0') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
