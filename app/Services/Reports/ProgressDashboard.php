<?php

namespace App\Services\Reports;

use App\Models\CatalogCourse;
use App\Models\ProgressReport;
use App\Models\ProgressReportItem;
use App\Support\Like;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cifras del tablero del seguimiento anual (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §2 y §7),
 * con los filtros de la URL: una consulta para el tablero (secciones en PHP) y otra para la lista de personas. Solo cuentan los cursos con "Cuenta para el
 * seguimiento" en el catálogo (o los que no están en el catálogo, con aviso en el corte).
 *
 * Realizado = aprobado + reprobado (curso al 100 % y evaluación presentada).
 */
class ProgressDashboard
{
    public const FILTERS = ['anio', 'curso', 'ruta', 'localidad', 'area', 'centro', 'especificos'];

    /** @var array<string, mixed> */
    public array $filters;

    /** @var array<string,string>|null */
    private ?array $labels = null;

    private ?array $rows = null;

    private ?array $years = null;

    private ?array $totals = null;

    public function __construct(public readonly ProgressReport $report, Request $request)
    {
        $years = $this->years();
        $year = (int) $request->query('anio');

        $this->filters = [
            'anio' => in_array($year, $years, true) ? $year : ($years[0] ?? null),
            'curso' => $this->text($request, 'curso'),
            'ruta' => in_array($request->query('ruta'), CatalogCourse::ROUTES, true) ? $request->query('ruta') : null,
            'localidad' => $this->text($request, 'localidad'),
            'area' => $this->text($request, 'area'),
            'centro' => $this->text($request, 'centro'),
            // §7.3: las comparaciones usan solo cursos generales salvo que se pida incluir los específicos.
            'especificos' => $request->boolean('especificos') ?: null,
        ];
    }

    /** Años del corte que cuentan (desde mera.progress.tracking_from_year), el más reciente primero. */
    public function years(): array
    {
        return $this->years ??= DB::table('progress_report_items')->where('progress_report_id', $this->report->id)
            ->where('year', '>=', (int) config('mera.progress.tracking_from_year', 2026))
            ->distinct()->orderByDesc('year')->pluck('year')->map(fn ($y) => (int) $y)->all();
    }

    /** Consulta base: asignaciones del corte, del año, que cuentan, con los filtros. */
    public function query(array $except = []): Builder
    {
        $f = $this->filters;

        return DB::table('progress_report_items as i')
            ->join('progress_report_people as p', 'p.id', '=', 'i.progress_report_person_id')
            ->where('i.progress_report_id', $this->report->id)
            ->where('i.year', $f['anio'] ?? 0)
            ->when($excluded = array_keys($this->excludedCourseIds()), fn ($q) => $q->where(fn ($w) => $w->whereNull('i.catalog_course_id')->orWhereNotIn('i.catalog_course_id', $excluded)))
            ->when($f['curso'] && ! in_array('curso', $except, true), fn ($q) => $q->where('i.course_code', $f['curso']))
            ->when($f['ruta'] && ! in_array('ruta', $except, true), fn ($q) => $q->where('i.course_code', 'like', $f['ruta'].'-%'))
            ->when($f['localidad'] && ! in_array('localidad', $except, true), fn ($q) => $q->where('p.locality', $f['localidad']))
            ->when($f['area'] && ! in_array('area', $except, true), fn ($q) => $q->where('p.area', $f['area']))
            ->when($f['centro'] && ! in_array('centro', $except, true), fn ($q) => $q->where('p.work_center', $f['centro']));
    }

    /**
     * Asignaciones del año que cuentan, SIN los demás filtros: una sola consulta (~60 mil filas
     * cortas) y las secciones se arman en PHP. Antes eran 8 consultas de ~1 s cada una.
     *
     * @return list<array{c:string,s:int,g:?float,h:float,p:float,m:?string,pid:int,loc:string,area:string,wc:?string}>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        // Sin JOIN: las personas del corte (~3 mil) van a un mapa y los cursos que no cuentan se
        // quitan por id. Así la consulta usa solo el índice (corte, año, curso) de las asignaciones.
        $people = DB::table('progress_report_people')->where('progress_report_id', $this->report->id)
            ->get(['id', 'locality', 'area', 'work_center', 'position'])->keyBy('id');
        $excluded = $this->excludedCourseIds();
        $catalog = CatalogCourse::query()->get(['id', 'scope', 'modality'])->keyBy('id');
        $rows = [];

        foreach (DB::table('progress_report_items')->where('progress_report_id', $this->report->id)->where('year', $this->filters['anio'] ?? 0)
            ->select('course_code', 'status', 'grade', 'hours', 'progress', 'started_at', 'progress_report_person_id', 'catalog_course_id')
            ->cursor() as $r) {
            if ($r->catalog_course_id !== null && isset($excluded[$r->catalog_course_id])) {
                continue;
            }
            $person = $people[$r->progress_report_person_id];
            $course = $r->catalog_course_id !== null ? ($catalog[$r->catalog_course_id] ?? null) : null;
            $rows[] = [
                'c' => $r->course_code, 's' => (int) $r->status, 'g' => $r->grade === null ? null : (float) $r->grade,
                'h' => (float) $r->hours, 'p' => (float) $r->progress, 'm' => $r->started_at ? substr((string) $r->started_at, 0, 7) : null,
                'pid' => (int) $r->progress_report_person_id, 'loc' => $person->locality, 'area' => $person->area, 'wc' => $person->work_center,
                'pos' => $person->position,
                // Sin curso en el catálogo: general (se avisa en el corte) y modalidad del código.
                'sc' => $course?->scope === CatalogCourse::SCOPE_SPECIFIC ? CatalogCourse::SCOPE_SPECIFIC : CatalogCourse::SCOPE_GENERAL,
                'mod' => $course?->modality ?? (explode('-', $r->course_code)[3] ?? ''),
            ];
        }

        return $this->rows = $rows;
    }

    /** @return array<int,true> ids del catálogo con "No cuenta para el seguimiento" */
    private function excludedCourseIds(): array
    {
        return array_fill_keys(CatalogCourse::query()->where('in_tracking', false)->pluck('id')->all(), true);
    }

    /** Filas con los filtros de la URL, menos los indicados (cada sección ignora el suyo). */
    private function filtered(array $except = []): array
    {
        $f = $this->filters;
        $checks = array_filter([
            'curso' => $f['curso'] ? fn ($r) => $r['c'] === $f['curso'] : null,
            'ruta' => $f['ruta'] ? fn ($r) => str_starts_with($r['c'], $f['ruta'].'-') : null,
            'localidad' => $f['localidad'] ? fn ($r) => $r['loc'] === $f['localidad'] : null,
            'area' => $f['area'] ? fn ($r) => $r['area'] === $f['area'] : null,
            'centro' => $f['centro'] ? fn ($r) => $r['wc'] === $f['centro'] : null,
        ]);
        $checks = array_diff_key($checks, array_flip($except));

        if ($checks === []) {
            return $this->rows();
        }

        return array_values(array_filter($this->rows(), function ($r) use ($checks) {
            foreach ($checks as $check) {
                if (! $check($r)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Filas para COMPARAR localidades, áreas y puestos (§7.3): solo cursos generales, salvo que se pida
     * incluir los específicos o se haya elegido un curso (entonces cuenta ese curso).
     */
    private function comparable(array $except = []): array
    {
        $rows = $this->filtered($except);

        if ($this->filters['especificos'] || $this->filters['curso']) {
            return $rows;
        }

        return array_values(array_filter($rows, fn ($r) => $r['sc'] === CatalogCourse::SCOPE_GENERAL));
    }

    /** ¿Las comparaciones van solo con cursos generales? (para las etiquetas del tablero) */
    public function generalsOnly(): bool
    {
        return ! $this->filters['especificos'] && ! $this->filters['curso'];
    }

    /** Totales para comparar (cursos y horas por persona): solo generales por defecto. */
    public function comparableTotals(): array
    {
        return $this->aggregate($this->comparable());
    }

    /**
     * Cursos específicos y presenciales (BL), §7.3: a quién van (áreas y puestos con más asignados),
     * cifras y modalidad. Sin ranking entre localidades.
     */
    public function specificCourses(): Collection
    {
        $labels = $this->courseLabels();
        $rows = array_filter($this->filtered(['curso']), fn ($r) => $r['sc'] === CatalogCourse::SCOPE_SPECIFIC || $r['mod'] === 'BL');
        $groups = [];
        foreach ($rows as $r) {
            $groups[$r['c']][] = $r;
        }

        $top = function (array $list, string $field): array {
            $counts = array_count_values(array_column($list, $field));
            arsort($counts);

            return array_slice($counts, 0, 3, true);
        };

        return collect($groups)->map(fn ($list, $code) => $this->aggregate($list) + [
            'k' => (string) $code,
            'label' => $labels[$code] ?? $code,
            'modality' => $list[0]['mod'],
            'specific' => $list[0]['sc'] === CatalogCourse::SCOPE_SPECIFIC,
            'areas' => $top($list, 'area'),
            'positions' => $top($list, 'pos'),
        ])->sortBy('label')->values();
    }

    /** @return array<string,int> clave de estado en la URL => asignaciones (pestañas de colaboradores) */
    public function statusCounts(): array
    {
        $by = array_count_values(array_column($this->filtered(), 's'));

        return array_map(fn ($status) => $by[$status] ?? 0, ProgressReportItem::STATUS_KEYS);
    }

    /** Totales (tarjetas). */
    public function totals(): array
    {
        return $this->totals ??= $this->aggregate($this->filtered());
    }

    /** Por curso, con su nombre del catálogo. */
    public function byCourse(): Collection
    {
        $labels = $this->courseLabels();

        $meta = [];
        foreach ($this->rows() as $r) {
            $meta[$r['c']] ??= ['specific' => $r['sc'] === CatalogCourse::SCOPE_SPECIFIC, 'modality' => $r['mod']];
        }

        return $this->groupBy($this->filtered(['curso']), 'c')
            ->map(fn ($r) => ['label' => $labels[$r['k']] ?? $r['k']] + ($meta[$r['k']] ?? []) + $r)
            ->sortByDesc('pct_done')->values();
    }

    /** Por localidad (ranking): solo localidades con al menos N personas. */
    public function byLocality(): Collection
    {
        $min = (int) config('mera.progress.dashboard.ranking_min_people', 10);

        return $this->groupBy($this->comparable(['localidad']), 'loc')
            ->filter(fn ($r) => $r['people'] >= $min)->sortByDesc('pct_done')->values();
    }

    public function byArea(): Collection
    {
        return $this->groupBy($this->comparable(['area']), 'area')->sortByDesc('pct_done')->values();
    }

    /** Avance por mes de inicio: iniciadas y realizadas por mes, y acumulado contra lo asignado. */
    public function byMonth(): Collection
    {
        $months = [];
        foreach ($this->filtered() as $r) {
            if ($r['m'] === null) {
                continue;
            }
            $months[$r['m']] ??= ['started' => 0, 'done' => 0];
            $months[$r['m']]['started']++;
            $months[$r['m']]['done'] += in_array($r['s'], [ProgressReportItem::STATUS_PASSED, ProgressReportItem::STATUS_FAILED], true) ? 1 : 0;
        }
        ksort($months);

        $total = max(1, $this->totals()['assigned']);
        $cumulative = 0;

        return collect($months)->map(function ($m, $month) use (&$cumulative, $total) {
            $cumulative += $m['done'];

            return ['month' => $month, 'started' => $m['started'], 'done' => $m['done'], 'cumulative' => $cumulative, 'pct_cumulative' => round($cumulative / $total * 100, 1)];
        })->values();
    }

    /** Calificaciones de quienes presentaron (realizado), por rango. */
    public function gradeBands(): array
    {
        $bands = ['b90' => 0, 'b80' => 0, 'b70' => 0, 'b60' => 0, 'b0' => 0, 'n' => 0];

        foreach ($this->filtered() as $r) {
            if ($r['g'] === null || ! in_array($r['s'], [ProgressReportItem::STATUS_PASSED, ProgressReportItem::STATUS_FAILED], true)) {
                continue;
            }
            $bands['n']++;
            $bands[match (true) {
                $r['g'] >= 90 => 'b90', $r['g'] >= 80 => 'b80', $r['g'] >= 70 => 'b70', $r['g'] >= 60 => 'b60', default => 'b0'
            }]++;
        }

        return $bands;
    }

    /** Dónde reprueban más (localidades con al menos N evaluados), mayor % de reprobados primero. */
    public function failingByLocality(): Collection
    {
        $min = (int) config('mera.progress.dashboard.failing_min_graded', 20);

        return $this->groupBy($this->comparable(['localidad']), 'loc')
            ->filter(fn ($r) => $r['done'] >= $min)
            ->map(fn ($r) => $r + ['pct_failed' => round($r['failed'] / max(1, $r['done']) * 100, 1)])
            ->sortByDesc('pct_failed')->values();
    }

    /** Opciones de los filtros. */
    public function options(): array
    {
        $people = DB::table('progress_report_people')->where('progress_report_id', $this->report->id);
        $courses = collect($this->rows())->pluck('c')->unique();
        $labels = $this->courseLabels();

        return [
            'years' => $this->years(),
            'courses' => $courses->mapWithKeys(fn ($code) => [$code => $labels[$code] ?? $code])->sort()->all(),
            'routes' => array_flip(CatalogCourse::ROUTES),
            'localities' => (clone $people)->distinct()->orderBy('locality')->pluck('locality')->all(),
            'areas' => (clone $people)->distinct()->orderBy('area')->pluck('area')->all(),
            'centers' => (clone $people)->whereNotNull('work_center')->distinct()->orderBy('work_center')->pluck('work_center')->all(),
        ];
    }

    /**
     * Personas × curso de un estado (consulta de colaboradores), con búsqueda por nombre o número.
     */
    public function people(?int $status, ?string $search): Builder
    {
        return $this->query()
            ->when($status, fn ($q) => $q->where('i.status', $status))
            ->when(filled($search), fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw("p.full_name LIKE ? ESCAPE '!'", ['%'.Like::escape(trim($search)).'%'])
                ->orWhere('p.employee_number', trim($search))))
            ->select('p.id as person_id', 'p.employee_number', 'p.full_name', 'p.locality', 'p.area', 'p.position', 'p.work_center',
                'i.course_code', 'i.progress', 'i.grade', 'i.status', 'i.started_at', 'i.completed_at')
            ->orderBy('p.locality')->orderBy('p.full_name')->orderBy('i.course_code');
    }

    /** @return array<string,string> código → nombre corto del catálogo */
    public function courseLabels(): array
    {
        return $this->labels ??= CatalogCourse::query()->get(['code', 'short_title', 'aliases'])
            ->flatMap(fn ($c) => collect([$c->code, ...($c->aliases ?? [])])->mapWithKeys(fn ($k) => [$k => $c->short_title]))
            ->all();
    }

    /** @return Collection<int, array> cifras por valor del campo, con 'k' = el valor */
    private function groupBy(array $rows, string $field): Collection
    {
        $groups = [];
        foreach ($rows as $r) {
            $groups[$r[$field]][] = $r;
        }

        return collect($groups)->map(fn ($list, $key) => $this->aggregate($list) + ['k' => (string) $key, 'label' => (string) $key])->values();
    }

    private function aggregate(array $rows): array
    {
        $sum = ['assigned' => 0, 'passed' => 0, 'failed' => 0, 'in_progress' => 0, 'not_started' => 0, 'grade_sum' => 0.0, 'grade_n' => 0, 'hours' => 0.0, 'hours_real' => 0.0];
        $people = [];

        foreach ($rows as $r) {
            $sum['assigned']++;
            $people[$r['pid']] = true;
            match ($r['s']) {
                ProgressReportItem::STATUS_PASSED => $sum['passed']++,
                ProgressReportItem::STATUS_FAILED => $sum['failed']++,
                ProgressReportItem::STATUS_IN_PROGRESS => $sum['in_progress']++,
                default => $sum['not_started']++,
            };
            if ($r['g'] !== null && in_array($r['s'], [ProgressReportItem::STATUS_PASSED, ProgressReportItem::STATUS_FAILED], true)) {
                $sum['grade_sum'] += $r['g'];
                $sum['grade_n']++;
            }
            $sum['hours'] += $r['h'];
            $sum['hours_real'] += $r['h'] * $r['p'];
        }

        return $this->normalize($sum + ['people' => count($people)]);
    }

    private function normalize(array $r): array
    {
        $assigned = (int) ($r['assigned'] ?? 0);
        $passed = (int) ($r['passed'] ?? 0);
        $failed = (int) ($r['failed'] ?? 0);
        $people = (int) ($r['people'] ?? 0);
        $gradeN = (int) ($r['grade_n'] ?? 0);
        $hours = (float) ($r['hours'] ?? 0);
        $pct = fn ($n) => $assigned ? round($n / $assigned * 100, 1) : 0.0;

        return [
            'assigned' => $assigned,
            'people' => $people,
            'passed' => $passed,
            'failed' => $failed,
            'done' => $passed + $failed,
            'in_progress' => (int) ($r['in_progress'] ?? 0),
            'not_started' => (int) ($r['not_started'] ?? 0),
            'pct_done' => $pct($passed + $failed),
            'pct_passed' => $pct($passed),
            'pct_not_started' => $pct((int) ($r['not_started'] ?? 0)),
            'avg_grade' => $gradeN ? round((float) $r['grade_sum'] / $gradeN, 1) : null,
            'courses_per_person' => $people ? round($assigned / $people, 1) : 0.0,
            'hours_per_person' => $people ? round($hours / $people, 1) : 0.0,
            'hours_real_per_person' => $people ? round((float) ($r['hours_real'] ?? 0) / $people, 1) : 0.0,
            'pct_hours' => $hours > 0 ? round((float) ($r['hours_real'] ?? 0) / $hours * 100, 1) : 0.0,
        ];
    }

    private function text(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key));

        return $value === '' ? null : mb_substr($value, 0, 150);
    }
}
