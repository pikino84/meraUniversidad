<?php

namespace App\Services\Courses;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Valida y extrae un paquete ZIP de curso en una carpeta temporal PRIVADA.
 *
 * Reglas:
 *  - Solo extensiones de la lista blanca (config mera.courses.allowed_extensions).
 *  - Sin rutas absolutas, unidades de Windows, barras invertidas, ".." ni symlinks.
 *  - Límite de archivos y de tamaño descomprimido (anti zip-bomb).
 *  - Debe contener el archivo de entrada (index.html), en la raíz o en una única carpeta raíz.
 */
class CoursePackageExtractor
{
    private const S_IFMT = 0170000;

    private const S_IFLNK = 0120000;

    /**
     * @return array{0:string,1:string} [carpeta temporal a limpiar, carpeta con el contenido del curso]
     */
    public function extract(string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new CoursePackageException('No se pudo abrir el archivo ZIP. Verifica que no esté dañado.');
        }

        try {
            $entries = $this->validEntries($zip);

            $tempRoot = rtrim(config('mera.courses.temp_root'), '/\\').DIRECTORY_SEPARATOR.Str::uuid();
            File::ensureDirectoryExists($tempRoot, 0755);

            if (! $zip->extractTo($tempRoot, $entries)) {
                File::deleteDirectory($tempRoot);
                throw new CoursePackageException('No se pudo descomprimir el archivo ZIP.');
            }
        } finally {
            $zip->close();
        }

        $contentDir = $this->contentDirectory($tempRoot);

        if ($contentDir === null) {
            File::deleteDirectory($tempRoot);
            throw new CoursePackageException(
                'El ZIP no contiene un '.config('mera.courses.entry_file').' en la raíz (ni en una única carpeta raíz).'
            );
        }

        return [$tempRoot, $contentDir];
    }

    /**
     * @return list<string> nombres de entradas a extraer
     */
    private function validEntries(ZipArchive $zip): array
    {
        $allowed = array_map('strtolower', config('mera.courses.allowed_extensions'));
        $maxFiles = (int) config('mera.courses.max_files');
        $maxBytes = (int) config('mera.courses.max_uncompressed_mb') * 1024 * 1024;

        $entries = [];
        $rejected = [];
        $totalBytes = 0;
        $fileCount = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];

            if ($this->isIgnored($name)) {
                continue;
            }

            $this->assertSafePath($name);

            if ($zip->getExternalAttributesIndex($i, $opsys, $attr)
                && $opsys === ZipArchive::OPSYS_UNIX
                && ((($attr >> 16) & self::S_IFMT) === self::S_IFLNK)) {
                throw new CoursePackageException('El ZIP contiene enlaces simbólicos, que no están permitidos.');
            }

            if (str_ends_with($name, '/')) {
                $entries[] = $name;

                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($extension, $allowed, true)) {
                $rejected[] = basename($name);

                continue;
            }

            $fileCount++;
            $totalBytes += (int) $stat['size'];
            $entries[] = $name;
        }

        if ($rejected !== []) {
            $sample = implode(', ', array_slice(array_unique($rejected), 0, 5));
            throw new CoursePackageException(
                "El ZIP contiene archivos no permitidos ({$sample}".(count($rejected) > 5 ? ', …' : '').'). '
                .'Solo se aceptan archivos web, multimedia y documentos.'
            );
        }

        if ($fileCount === 0) {
            throw new CoursePackageException('El archivo ZIP está vacío.');
        }

        if ($fileCount > $maxFiles) {
            throw new CoursePackageException("El ZIP contiene {$fileCount} archivos; el máximo permitido es {$maxFiles}.");
        }

        if ($totalBytes > $maxBytes) {
            throw new CoursePackageException(
                'El contenido descomprimido excede el máximo permitido de '.config('mera.courses.max_uncompressed_mb').' MB.'
            );
        }

        return $entries;
    }

    private function assertSafePath(string $name): void
    {
        $invalid = $name === ''
            || str_contains($name, "\0")
            || str_contains($name, '\\')
            || str_starts_with($name, '/')
            || preg_match('/^[a-zA-Z]:/', $name) === 1
            || in_array('..', explode('/', $name), true);

        if ($invalid) {
            throw new CoursePackageException('El archivo ZIP contiene rutas no válidas.');
        }
    }

    private function isIgnored(string $name): bool
    {
        foreach (config('mera.courses.ignored_entries') as $pattern) {
            if (str_ends_with($pattern, '/') ? str_starts_with($name, $pattern) : basename($name) === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * Carpeta que contiene el archivo de entrada: la raíz, o la única carpeta raíz si el
     * ZIP se comprimió "con carpeta".
     */
    private function contentDirectory(string $root): ?string
    {
        $entry = config('mera.courses.entry_file');

        if (File::exists($root.DIRECTORY_SEPARATOR.$entry)) {
            return $root;
        }

        $directories = File::directories($root);

        if (count($directories) === 1 && count(File::files($root)) === 0
            && File::exists($directories[0].DIRECTORY_SEPARATOR.$entry)) {
            return $directories[0];
        }

        return null;
    }
}
