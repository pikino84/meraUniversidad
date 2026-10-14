<?php

namespace App\Services\Courses;

use App\Models\Course;
use App\Models\CourseGroup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Acomodo de módulos en carpetas de curso (CLAUDE.md §10).
 * Solo cambia columnas de la BD (course_group_id, language, position): nunca archivos ni URLs.
 */
class CourseGroupOrganizer
{
    /**
     * Mueve varios módulos a una carpeta + idioma (o a "Sin carpeta" con $groupId = null).
     * Se agregan al final: por el número de módulo del título si lo trae y, si no, en el orden recibido.
     *
     * @param  list<int>  $courseIds
     */
    public function moveModules(array $courseIds, ?int $groupId, ?string $language): int
    {
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));

        return DB::transaction(function () use ($courseIds, $groupId, $language) {
            // F3: si el título trae el número ("MODULE 2"), se ordena por él; si no, en el orden recibido.
            $courses = Course::whereIn('id', $courseIds)->get()->sort(fn ($a, $b) => [$a->titleModuleNumber() ?? PHP_INT_MAX, array_search($a->id, $courseIds, true)]
                <=> [$b->titleModuleNumber() ?? PHP_INT_MAX, array_search($b->id, $courseIds, true)]);
            $previous = $courses->map(fn ($c) => [$c->course_group_id, $c->language])->unique()->all();

            $next = $groupId ? $this->lastPosition($groupId, $language) : null;

            foreach ($courses as $course) {
                // Si ya está en ese mismo destino, conserva su lugar.
                if ($groupId && $course->course_group_id === $groupId && $course->language === $language) {
                    continue;
                }

                $course->forceFill([
                    'course_group_id' => $groupId,
                    'language' => $groupId ? $language : null,
                    'position' => $groupId ? ++$next : null,
                ])->save();
            }

            foreach ($previous as [$oldGroup, $oldLanguage]) {
                $this->normalize($oldGroup, $oldLanguage);
            }

            if ($groupId) {
                $this->normalize($groupId, $language);
            }

            return $courses->count();
        });
    }

    /**
     * Ubica un módulo desde su formulario: carpeta + idioma + posición opcional.
     * Sin carpeta → "Sin carpeta". Sin posición → al final (o donde ya estaba).
     */
    public function assign(Course $course, ?int $groupId, ?string $language, ?int $position): void
    {
        $sameSpot = $groupId && $course->course_group_id === $groupId && $course->language === $language;

        if (! $sameSpot) {
            $this->moveModules([$course->id], $groupId, $language);
            $course->refresh();
        }

        if ($groupId && $position) {
            $this->placeModule($course, $position);
        }
    }

    /**
     * Sube o baja un módulo una posición dentro de su carpeta e idioma.
     */
    public function moveModule(Course $course, string $direction): void
    {
        if (! $course->course_group_id) {
            return;
        }

        DB::transaction(function () use ($course, $direction) {
            $this->normalize($course->course_group_id, $course->language);

            $siblings = $this->siblings($course->course_group_id, $course->language)->values();
            $index = $siblings->search(fn ($c) => $c->id === $course->id);
            $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! isset($siblings[$swapWith])) {
                return;
            }

            $this->swapPositions($siblings[$index], $siblings[$swapWith]);
        });
    }

    /**
     * Reordena todos los módulos de una carpeta e idioma según la lista de IDs (arrastrar y
     * soltar, U18). La lista debe contener exactamente los módulos de ese grupo; si no, no hace
     * nada y devuelve false (p. ej. otra persona movió un módulo mientras tanto).
     *
     * @param  list<int>  $orderedIds
     */
    public function reorder(CourseGroup $group, string $language, array $orderedIds): bool
    {
        return DB::transaction(function () use ($group, $language, $orderedIds) {
            $siblings = $this->siblings($group->id, $language)->keyBy('id');
            $ids = array_map('intval', $orderedIds);

            if (count($ids) !== $siblings->count() || array_diff($ids, $siblings->keys()->all()) !== []) {
                return false;
            }

            $this->writePositions(collect($ids)->map(fn ($id) => $siblings[$id])->values());

            return true;
        });
    }

    /**
     * Coloca un módulo en una posición concreta (1 = primero) dentro de su carpeta e idioma.
     */
    public function placeModule(Course $course, ?int $position): void
    {
        if (! $course->course_group_id) {
            return;
        }

        DB::transaction(function () use ($course, $position) {
            $siblings = $this->siblings($course->course_group_id, $course->language)
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
     * Borra la carpeta y regresa sus módulos a "Sin carpeta" (no borra ningún módulo).
     * Explícito: no depende de que el motor de BD aplique el ON DELETE SET NULL.
     */
    public function deleteGroup(CourseGroup $group): int
    {
        return DB::transaction(function () use ($group) {
            $released = Course::where('course_group_id', $group->id)
                ->update(['course_group_id' => null, 'language' => null, 'position' => null]);

            $group->delete();
            Cache::forget(Course::API_CACHE_KEY);

            return $released;
        });
    }

    /**
     * Reasigna posiciones 1..n sin huecos, respetando el orden actual.
     */
    public function normalize(?int $groupId, ?string $language): void
    {
        if (! $groupId) {
            return;
        }

        $this->writePositions($this->siblings($groupId, $language)->values());
    }

    private function siblings(int $groupId, ?string $language)
    {
        return Course::query()
            ->where('course_group_id', $groupId)
            ->when($language, fn ($q) => $q->where('language', $language), fn ($q) => $q->whereNull('language'))
            ->ordered()
            ->get();
    }

    private function lastPosition(int $groupId, ?string $language): int
    {
        return (int) Course::where('course_group_id', $groupId)
            ->when($language, fn ($q) => $q->where('language', $language), fn ($q) => $q->whereNull('language'))
            ->max('position');
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
