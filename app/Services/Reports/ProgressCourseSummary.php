<?php

namespace App\Services\Reports;

use App\Models\CatalogCourse;
use App\Models\CourseGroupVersion;
use App\Models\ProgressReport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Conexión entre el seguimiento anual y los reportes de cada curso (decisión del usuario, 2026-10-20;
 * INSTRUCTIVO_REPORTE_PERSONAS.md §5): la pestaña Reportes de una versión muestra su curso en el último
 * corte del seguimiento, y el seguimiento enlaza a las versiones de carpeta del curso. El vínculo es el
 * código del catálogo de la versión.
 */
class ProgressCourseSummary
{
    /**
     * state: no_code (la versión no tiene código) | not_in_catalog | not_tracked (no cuenta o es de un año
     * anterior) | not_in_cut (ningún corte lo trae) | ok.
     *
     * @return array{state: string, course?: CatalogCourse, report?: ProgressReport, totals?: array, survey?: mixed, url?: string}
     */
    public static function forVersion(CourseGroupVersion $version): array
    {
        if (blank($version->catalog_code)) {
            return ['state' => 'no_code'];
        }

        $course = $version->catalogCourse();
        if (! $course) {
            return ['state' => 'not_in_catalog'];
        }

        if (! $course->in_tracking || $course->year < (int) config('mera.progress.tracking_from_year', 2026)) {
            return ['state' => 'not_tracked', 'course' => $course];
        }

        $report = ProgressReport::query()
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('progress_report_items')
                ->whereColumn('progress_report_items.progress_report_id', 'progress_reports.id')
                ->where('progress_report_items.course_code', $course->code))
            ->orderByDesc('cut_date')->orderByDesc('id')->first();

        if (! $report) {
            return ['state' => 'not_in_cut', 'course' => $course];
        }

        $query = ['curso' => $course->code, 'anio' => $course->year];

        return [
            'state' => 'ok',
            'course' => $course,
            'report' => $report,
            'totals' => (new ProgressDashboard($report, Request::create('/', 'GET', $query)))->totals(),
            'survey' => $report->surveys()->where('course_code', $course->code)->first(),
            'url' => route('progress.show', ['report' => $report] + $query),
        ];
    }

    /** Versiones de carpeta de un curso del catálogo (por su código o sus códigos de la plataforma). */
    public static function versionsFor(string $courseCode): Collection
    {
        $course = CatalogCourse::resolve($courseCode);
        $codes = array_values(array_unique(array_filter([$courseCode, $course?->code, ...($course?->aliases ?? [])])));

        return CourseGroupVersion::with(['group', 'market'])->whereIn('catalog_code', $codes)->ordered()->get();
    }
}
