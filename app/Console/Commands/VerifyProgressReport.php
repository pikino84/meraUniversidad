<?php

namespace App\Console\Commands;

use App\Models\CatalogCourse;
use App\Models\ProgressReportItem;
use App\Services\Reports\ProgressReportBuilder;
use App\Services\Reports\ReportFormatException;
use Illuminate\Console\Command;

/**
 * Arma un corte del seguimiento EN MEMORIA (no escribe nada) e imprime sus cifras, para compararlas
 * con las de docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §9. Solo agregados: nunca nombres.
 *
 *   php artisan mera:progress-verify "<Reporte de progreso…>.xlsx" "<Reporte de personal…>.xlsx" [--year=2026] [--course=NRM-HIG-002-EL-26]
 */
class VerifyProgressReport extends Command
{
    protected $signature = 'mera:progress-verify
        {progreso : Reporte de progreso de capacitaciones (.xlsx)}
        {personal : Reporte de personal (.xlsx)}
        {--year= : Año a resumir (por defecto, todos los que cuentan)}
        {--course=* : Además, el detalle de estos códigos}';

    protected $description = 'Arma un corte del seguimiento anual en memoria e imprime sus cifras (solo lectura)';

    public function handle(ProgressReportBuilder $builder): int
    {
        $start = microtime(true);

        try {
            $data = $builder->build((string) $this->argument('progreso'), (string) $this->argument('personal'));
        } catch (ReportFormatException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $seconds = round(microtime(true) - $start, 1);
        $tracked = CatalogCourse::query()->where('in_tracking', false)->pluck('id')->flip();
        $from = (int) config('mera.progress.tracking_from_year', 2026);
        $items = collect($data['items'])
            ->filter(fn ($i) => $i['year'] >= $from && ! isset($tracked[$i['catalog_course_id'] ?? -1]))
            ->when($this->option('year'), fn ($c) => $c->where('year', (int) $this->option('year')));

        $this->table(['Indicador', 'Valor'], [
            ['Filas leídas', number_format($data['summary']['rows'])],
            ['Asignaciones que cuentan', number_format($items->count())],
            ['Cursos', $items->pluck('course_code')->unique()->count()],
            ...$this->figures($items),
            ['Tiempo', "{$seconds} s · ".round(memory_get_peak_usage(true) / 1048576).' MB'],
        ]);

        foreach ($data['warnings'] as $warning) {
            $this->warn(' - '.$warning);
        }

        foreach ((array) $this->option('course') as $code) {
            $course = CatalogCourse::resolve($code);
            $subset = $items->filter(fn ($i) => $i['course_code'] === ($course?->code ?? $code));
            $this->line("\n{$code}".($course ? " · {$course->short_title}" : ''));
            $this->table(['Indicador', 'Valor'], $this->figures($subset));
        }

        return self::SUCCESS;
    }

    private function figures($items): array
    {
        $n = max(1, $items->count());
        $by = $items->countBy('status');
        $done = ($by[ProgressReportItem::STATUS_PASSED] ?? 0) + ($by[ProgressReportItem::STATUS_FAILED] ?? 0);
        $graded = $items->whereIn('status', [ProgressReportItem::STATUS_PASSED, ProgressReportItem::STATUS_FAILED])->whereNotNull('grade');
        $pct = fn ($v) => number_format($v).' ('.round($v / $n * 100, 1).' %)';

        return [
            ['Asignaciones', number_format($items->count())],
            ['Personas', number_format($items->pluck('employee_number')->unique()->count())],
            ['Al 100 % (progreso 1)', $pct($items->where('progress', '>=', 1)->count())],
            ['Realizado (100 % + evaluación)', $pct($done)],
            ['Aprobados', $pct($by[ProgressReportItem::STATUS_PASSED] ?? 0)],
            ['Reprobados', $pct($by[ProgressReportItem::STATUS_FAILED] ?? 0)],
            ['En curso', $pct($by[ProgressReportItem::STATUS_IN_PROGRESS] ?? 0)],
            ['Sin iniciar', $pct($by[ProgressReportItem::STATUS_NOT_STARTED] ?? 0)],
            ['Promedio (realizado)', $graded->count() ? round($graded->avg('grade'), 1).' (n = '.number_format($graded->count()).')' : '—'],
        ];
    }
}
