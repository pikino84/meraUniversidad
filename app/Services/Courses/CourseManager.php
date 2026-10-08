<?php

namespace App\Services\Courses;

use App\Models\Course;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Casos de uso de cursos (alta, edición, baja) con manejo de archivos "todo o nada":
 * primero se prepara todo lo nuevo, luego se guarda en BD y solo al final se retira lo viejo.
 * Si algo falla, se revierte lo nuevo y el curso queda como estaba.
 *
 * Pensado para poder moverse a un Job en cola sin cambios (recibe rutas, no el Request).
 */
class CourseManager
{
    public function __construct(
        private readonly CourseStorage $storage,
        private readonly CoursePackageExtractor $extractor,
    ) {}

    /**
     * @param  array{name:string,description:string,category_id:?int}  $data
     */
    public function create(array $data, UploadedFile $cover, UploadedFile $package): Course
    {
        $this->storage->ensureCoursesRoot();

        $created = [];
        $tempRoot = null;

        try {
            $folder = $this->storage->newFolderName();
            [$tempRoot, $content] = $this->extractor->extract($package->getRealPath());

            $this->storage->moveDirectory($content, $this->storage->resolve($this->storage->relative($folder)));
            $created[] = $this->storage->relative($folder);

            $coverPath = $this->storeCover($cover, $folder);
            $created[] = $coverPath;

            return DB::transaction(fn () => Course::create([
                'name' => $data['name'],
                'description' => $data['description'],
                'category_id' => $data['category_id'] ?? null,
                'cover_image' => $coverPath,
                'path' => $this->storage->relative($folder),
            ]));
        } catch (Throwable $e) {
            array_map(fn ($path) => $this->storage->deleteQuietly($path), $created);

            throw $e;
        } finally {
            $this->cleanupTemp($tempRoot);
        }
    }

    /**
     * @param  array{name:string,description:string,category_id:?int}  $data
     */
    public function update(Course $course, array $data, ?UploadedFile $cover = null, ?UploadedFile $package = null): Course
    {
        $this->storage->ensureCoursesRoot();

        $created = [];
        $retire = [];
        $tempRoot = null;
        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'],
            'category_id' => $data['category_id'] ?? null,
        ];

        try {
            if ($package) {
                $folder = $this->storage->newFolderName();
                [$tempRoot, $content] = $this->extractor->extract($package->getRealPath());

                $this->storage->moveDirectory($content, $this->storage->resolve($this->storage->relative($folder)));
                $created[] = $attributes['path'] = $this->storage->relative($folder);
                $retire[] = $course->path;

                // Cursos antiguos guardan la portada DENTRO de su carpeta: se rescata antes de retirarla.
                if (! $cover && $this->isInside($course->cover_image, $course->path)) {
                    $created[] = $attributes['cover_image'] = $this->copyCover($course->cover_image, $folder);
                }
            }

            if ($cover) {
                $folderName = basename($attributes['path'] ?? $course->path);
                $created[] = $attributes['cover_image'] = $this->storeCover($cover, $folderName);

                if (! $this->isInside($course->cover_image, $course->path)) {
                    $retire[] = $course->cover_image;
                }
            }

            DB::transaction(fn () => $course->update($attributes));
        } catch (Throwable $e) {
            array_map(fn ($path) => $this->storage->deleteQuietly($path), $created);

            throw $e;
        } finally {
            $this->cleanupTemp($tempRoot);
        }

        // Lo viejo se retira solo cuando lo nuevo ya quedó guardado.
        $this->retire($retire);

        return $course;
    }

    public function delete(Course $course): void
    {
        $paths = [$course->path];

        if (! $this->isInside($course->cover_image, $course->path)) {
            $paths[] = $course->cover_image;
        }

        DB::transaction(fn () => $course->delete());

        $this->retire($paths);
    }

    /**
     * Guarda la portada con la extensión DETECTADA por contenido (nunca la del cliente) y
     * un sufijo aleatorio que además evita caché vieja del navegador.
     */
    private function storeCover(UploadedFile $cover, string $folder): string
    {
        $extension = match ($cover->guessExtension()) {
            'jpeg', 'jpg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            default => throw new CoursePackageException('La portada debe ser una imagen JPG, PNG o WEBP.'),
        };

        $name = "{$folder}-cover-".Str::lower(Str::random(6)).".{$extension}";
        $cover->move($this->storage->coursesRoot(), $name);

        return $this->storage->relative($name);
    }

    private function copyCover(string $relativeCover, string $folder): string
    {
        if (! File::exists($this->storage->resolve($relativeCover))) {
            return $relativeCover; // no hay archivo que rescatar (p. ej. entorno local incompleto)
        }

        $extension = strtolower(pathinfo($relativeCover, PATHINFO_EXTENSION)) ?: 'png';
        $name = "{$folder}-cover-".Str::lower(Str::random(6)).".{$extension}";

        File::copy($this->storage->resolve($relativeCover), $this->storage->coursesRoot().DIRECTORY_SEPARATOR.$name);

        return $this->storage->relative($name);
    }

    private function isInside(?string $file, ?string $folder): bool
    {
        return $file && $folder && str_starts_with(str_replace('\\', '/', $file), rtrim($folder, '/').'/');
    }

    /**
     * Manda a la papelera archivos que ya no usa ningún curso. Un fallo aquí no debe
     * revertir la operación (la BD ya es correcta), solo se registra.
     */
    private function retire(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            try {
                $this->storage->trash($path);
            } catch (Throwable $e) {
                Log::warning("No se pudo mandar a la papelera [{$path}]: {$e->getMessage()}");
            }
        }
    }

    private function cleanupTemp(?string $tempRoot): void
    {
        if ($tempRoot && File::isDirectory($tempRoot)) {
            File::deleteDirectory($tempRoot);
        }
    }
}
