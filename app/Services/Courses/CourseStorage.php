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
    public const HTACCESS_MARKER = '# mera:harden-storage v2';

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
        // El servidor web (LiteSpeed lee los estáticos con otro usuario) debe poder leerlo:
        // con un umask restrictivo (p. ej. 077) quedaba en 600 y se ignoraba en silencio.
        @chmod($path, 0644);

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

# 1) mod_rewrite: es lo que LiteSpeed (producción) sí respeta; RemoveHandler y
#    "Require all denied" pueden ser ignorados con LSAPI. Responde 403.
RewriteEngine On
RewriteRule \.(php[0-9]?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp)$ - [F,NC,L]
RewriteRule (^|/)\. - [F,L]

# 2) Desactiva el intérprete PHP (mod_php). Apache también lo cubre con FilesMatch.
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
     * `php artisan mera:purge-course-trash`). Devuelve el nombre dentro de la papelera
     * (para poder restaurarlo) o null si no había nada que mover.
     */
    public function trash(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }

        $absolute = $this->resolve($relativePath);

        if (! File::exists($absolute)) {
            return null;
        }

        $trashRoot = $this->trashRoot();
        $name = now()->format('Ymd_His').'_'.Str::random(4).'_'.basename($absolute);
        $target = $trashRoot.DIRECTORY_SEPARATOR.$name;

        File::ensureDirectoryExists($trashRoot, 0755);

        if (File::isDirectory($absolute)) {
            $this->moveDirectory($absolute, $target);
        } else {
            File::move($absolute, $target);
            // rename conserva la fecha original del archivo: sin esto, la purga podría
            // borrarlo en la siguiente corrida aunque se acabe de mandar a la papelera.
            touch($target);
        }

        return $name;
    }

    /**
     * ¿Sigue en la papelera? (la purga borra lo que lleva más de N días).
     */
    public function inTrash(string $trashName): bool
    {
        return $this->isSafeTrashName($trashName) && File::exists($this->trashRoot().DIRECTORY_SEPARATOR.$trashName);
    }

    /**
     * Devuelve un elemento de la papelera a su ruta original (F2). Falla si ya no está en la
     * papelera o si la ruta original quedó ocupada, para nunca sobrescribir otro curso.
     */
    public function restoreFromTrash(string $trashName, string $relativePath): void
    {
        if (! $this->inTrash($trashName)) {
            throw new RuntimeException("[{$trashName}] ya no está en la papelera.");
        }

        $source = $this->trashRoot().DIRECTORY_SEPARATOR.$trashName;
        $target = $this->resolve($relativePath);

        if (File::exists($target)) {
            throw new RuntimeException("La ruta original [{$relativePath}] ya está ocupada.");
        }

        File::ensureDirectoryExists(dirname($target), 0755);

        if (File::isDirectory($source)) {
            $this->moveDirectory($source, $target);
        } else {
            File::move($source, $target);
        }
    }

    private function trashRoot(): string
    {
        return rtrim(config('mera.courses.trash_root'), '/\\');
    }

    /** Solo nombres simples generados por trash() (sin rutas ni ".."). */
    private function isSafeTrashName(string $name): bool
    {
        return $name !== '' && $name === basename($name) && ! str_contains($name, '..');
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
     * Revisión de SOLO LECTURA de los archivos de un curso (CLAUDE.md §12, F1).
     *
     * @return array{content:bool,cover:bool} true = ese archivo FALTA
     */
    public function missingFiles(?string $path, ?string $cover): array
    {
        $exists = function (?string $relative): bool {
            if (! $relative) {
                return false;
            }

            try {
                return File::exists($this->resolve($relative));
            } catch (RuntimeException) {
                return false;
            }
        };

        $entry = $path ? rtrim($path, '/').'/'.config('mera.courses.entry_file') : null;

        return [
            'content' => ! $exists($entry),
            'cover' => ! $exists($cover),
        ];
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
