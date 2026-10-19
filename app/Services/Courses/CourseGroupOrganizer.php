<?php

namespace App\Services\Courses;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseGroupVersion;
use App\Models\Market;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Acomodo de módulos en carpetas de curso (CLAUDE.md §10) por año y versión por país
 * (docs/carpetas/INSTRUCTIVO_VERSIONES.md). La unidad de orden es la VERSIÓN (carpeta + año +
 * país). Solo cambia columnas de la BD: nunca archivos ni URLs de módulos.
 *
 * Un módulo con versión también guarda `course_group_id` (el de la versión) y `language` (el de
 * su país), porque la API y los filtros del panel los siguen usando (CLAUDE.md §10).
 */
class CourseGroupOrganizer
{
    /**
     * Versión de una carpeta para un año y un país; se crea si no existe (apagada y vacía).
     */
    public function version(CourseGroup $group, int $year, Market $market): CourseGroupVersion
    {
        return $group->versions()->firstOrCreate(
            ['year' => $year, 'market_id' => $market->id],
            ['position' => 0],
        );
    }

    /**
     * Mueve varios módulos a una versión (o a "Sin carpeta" con $version = null).
     * Se agregan al final: por el número de módulo del título si lo trae y, si no, en el orden recibido.
     *
     * @param  list<int>  $courseIds
     */
    public function moveModules(array $courseIds, ?CourseGroupVersion $version): int
    {
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));

        return DB::transaction(function () use ($courseIds, $version) {
            // F3: si el título trae el número ("MODULE 2"), se ordena por él; si no, en el orden recibido.
            $courses = Course::whereIn('id', $courseIds)->get()->sort(fn ($a, $b) => [$a->titleModuleNumber() ?? PHP_INT_MAX, array_search($a->id, $courseIds, true)]
                <=> [$b->titleModuleNumber() ?? PHP_INT_MAX, array_search($b->id, $courseIds, true)]);
            $previous = $courses->pluck('course_group_version_id')->filter()->unique()->all();

            $next = $version ? $this->lastPosition($version->id) : null;
            $language = $version?->language();

            foreach ($courses as $course) {
                // Si ya está en esa misma versión, conserva su lugar.
                if ($version && $course->course_group_version_id === $version->id) {
                    continue;
                }

                $course->forceFill([
                    'course_group_id' => $version?->course_group_id,
                    'course_group_version_id' => $version?->id,
                    'language' => $language,
                    'position' => $version ? ++$next : null,
                ])->save();
            }

            foreach ($previous as $oldVersion) {
                $this->normalize($oldVersion);
            }

            if ($version) {
                $this->normalize($version->id);
            }

            return $courses->count();
        });
    }

    /**
     * Ubica un módulo desde su formulario: versión + posición opcional.
     * Sin versión → "Sin carpeta". Sin posición → al final (o donde ya estaba).
     */
    public function assign(Course $course, ?CourseGroupVersion $version, ?int $position): void
    {
        if ($course->course_group_version_id !== $version?->id || ($version === null && $course->course_group_id)) {
            $this->moveModules([$course->id], $version);
            $course->refresh();
        }

        if ($version && $position) {
            $this->placeModule($course, $position);
        }
    }

    /**
     * Sube o baja un módulo una posición dentro de su versión.
     */
    public function moveModule(Course $course, string $direction): void
    {
        if (! $course->course_group_version_id) {
            return;
        }

        DB::transaction(function () use ($course, $direction) {
            $this->normalize($course->course_group_version_id);

            $siblings = $this->siblings($course->course_group_version_id)->values();
            $index = $siblings->search(fn ($c) => $c->id === $course->id);
            $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! isset($siblings[$swapWith])) {
                return;
            }

            $this->swapPositions($siblings[$index], $siblings[$swapWith]);
        });
    }

    /**
     * Reordena todos los módulos de una versión según la lista de IDs (arrastrar y soltar, U18).
     * La lista debe contener exactamente los módulos de esa versión; si no, no hace nada y
     * devuelve false (p. ej. otra persona movió un módulo mientras tanto).
     *
     * @param  list<int>  $orderedIds
     */
    public function reorder(CourseGroupVersion $version, array $orderedIds): bool
    {
        return DB::transaction(function () use ($version, $orderedIds) {
            $siblings = $this->siblings($version->id)->keyBy('id');
            $ids = array_map('intval', $orderedIds);

            if (count($ids) !== $siblings->count() || array_diff($ids, $siblings->keys()->all()) !== []) {
                return false;
            }

            $this->writePositions(collect($ids)->map(fn ($id) => $siblings[$id])->values());

            return true;
        });
    }

    /**
     * Coloca un módulo en una posición concreta (1 = primero) dentro de su versión.
     */
    public function placeModule(Course $course, ?int $position): void
    {
        if (! $course->course_group_version_id) {
            return;
        }

        DB::transaction(function () use ($course, $position) {
            $siblings = $this->siblings($course->course_group_version_id)
                ->reject(fn ($c) => $c->id === $course->id)
                ->values();

            $index = $position ? max(0, min($position - 1, $siblings->count())) : $siblings->count();
            $siblings->splice($index, 0, [$course]);

            $this->writePositions($siblings);
        });
    }

    public function moveGroup(CourseGroup $group, string $direction): void
    {
        DB::transaction(function () use ($group, $direction) {
            $groups = CourseGroup::orderBy('position')->orderBy('name')->get()->values();
            $groups->each(fn ($g, $i) => $g->position !== $i + 1 && $g->forceFill(['position' => $i + 1])->saveQuietly());

            $index = $groups->search(fn ($g) => $g->id === $group->id);
            $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! isset($groups[$swapWith])) {
                return;
            }

            [$a, $b] = [$groups[$index], $groups[$swapWith]];
            [$pa, $pb] = [$a->position, $b->position];
            $a->forceFill(['position' => $pb])->saveQuietly();
            $b->forceFill(['position' => $pa])->saveQuietly();
            Cache::forget(Course::API_CACHE_KEY);
        });
    }

    /**
     * Borra la carpeta (y sus versiones) y regresa sus módulos a "Sin carpeta" (no borra ningún
     * módulo). Explícito: no depende de que el motor de BD aplique los ON DELETE.
     */
    public function deleteGroup(CourseGroup $group): int
    {
        return DB::transaction(function () use ($group) {
            $released = Course::where('course_group_id', $group->id)->update([
                'course_group_id' => null, 'course_group_version_id' => null, 'language' => null, 'position' => null,
            ]);

            $group->reports()->update(['course_group_version_id' => null]);
            $group->versions()->delete();
            $group->delete();
            Cache::forget(Course::API_CACHE_KEY);

            return $released;
        });
    }

    /**
     * Borra una versión vacía (sin módulos ni cortes). Devuelve false si tiene algo.
     */
    public function deleteVersion(CourseGroupVersion $version): bool
    {
        if ($version->modules()->exists() || $version->reports()->exists()) {
            return false;
        }

        $version->delete();

        return true;
    }

    /**
     * "+ Año": crea las versiones de un año copiando solo la ESTRUCTURA de otro (países, código
     * del catálogo vacío, título, descripción y links de evaluación/encuesta), sin módulos y
     * apagadas (decisión del usuario, INSTRUCTIVO_VERSIONES.md §9.5). Sin año de origen: vacío.
     *
     * @return list<CourseGroupVersion> las versiones creadas (las que ya existían no se tocan)
     */
    public function createYear(CourseGroup $group, int $year, ?int $copyFrom, array $marketIds = []): array
    {
        return DB::transaction(function () use ($group, $year, $copyFrom, $marketIds) {
            $sources = $copyFrom ? $group->versions()->where('year', $copyFrom)->get() : collect();
            $created = [];

            $targets = $sources->isNotEmpty()
                ? $sources->map(fn ($v) => [$v->market_id, $v])
                : collect($marketIds)->map(fn ($id) => [(int) $id, null]);

            foreach ($targets as [$marketId, $source]) {
                if ($group->versions()->where('year', $year)->where('market_id', $marketId)->exists()) {
                    continue;
                }

                $created[] = $group->versions()->create([
                    'year' => $year,
                    'market_id' => $marketId,
                    'title' => $source?->title,
                    'description' => $source?->description,
                    'evaluation_url' => $source?->evaluation_url,
                    'survey_url' => $source?->survey_url,
                ]);
            }

            return $created;
        });
    }

    /**
     * Reasigna posiciones 1..n sin huecos dentro de una versión, respetando el orden actual.
     */
    public function normalize(?int $versionId): void
    {
        if (! $versionId) {
            return;
        }

        $this->writePositions($this->siblings($versionId)->values());
    }

    private function siblings(int $versionId)
    {
        return Course::query()->where('course_group_version_id', $versionId)->ordered()->get();
    }

    private function lastPosition(int $versionId): int
    {
        return (int) Course::where('course_group_version_id', $versionId)->max('position');
    }

    private function writePositions($ordered): void
    {
        foreach ($ordered as $i => $course) {
            if ($course->position !== $i + 1) {
                $course->forceFill(['position' => $i + 1])->save();
            }
        }
    }

    private function swapPositions(Course $a, Course $b): void
    {
        [$pa, $pb] = [$a->position, $b->position];
        $a->forceFill(['position' => $pb])->save();
        $b->forceFill(['position' => $pa])->save();
    }
}
