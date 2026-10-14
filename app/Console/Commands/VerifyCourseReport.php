<?php

namespace App\Console\Commands;

use App\Services\Reports\CourseReportBuilder;
use App\Services\Reports\ReportFormatException;
use Illuminate\Console\Command;

/**
 * Criterio de aceptación de los reportes (INSTRUCTIVO_REPORTES.md §9). SOLO LECTURA: arma el
 * reporte con los 3 Excel y lo compara fila por fila contra la salida del script de referencia
 * (el HTML que genera generar_reporte.py). No guarda nada.
 *
 *   php artisan mera:report-verify <personal.xlsx> <inscripciones.xlsx> <encuesta.xlsx> \
 *       --expected=storage/app/reportes/referencia/esperado.html [--survey=higiene]
 */
class VerifyCourseReport extends Command
{
    protected $signature = 'mera:report-verify
        {personal : Reporte de personal (.xlsx)}
        {inscripciones : Reporte de inscripciones (.xlsx)}
        {encuesta : Encuesta de satisfacción (.xlsx)}
        {--expected= : HTML generado por generar_reporte.py}
        {--survey= : Clave de config/report_surveys.php (por defecto, la de mera.reports.default_survey)}';

    protected $description = 'Compara el reporte armado en PHP contra la salida del script de referencia (solo lectura)';

    public function handle(CourseReportBuilder $builder): int
    {
        $survey = config('report_surveys.'.($this->option('survey') ?: config('mera.reports.default_survey')));

        if (! $survey) {
            $this->error('No existe esa encuesta en config/report_surveys.php.');

            return self::FAILURE;
        }

        $start = microtime(true);

        try {
            $data = $builder->build($this->argument('personal'), $this->argument('inscripciones'), $this->argument('encuesta'), $survey);
        } catch (ReportFormatException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $s = $data['summary'];
        $this->table(['Indicador', 'Valor'], [
            ['Asignados', number_format($s['asignados'])],
            ['Completaron', number_format($s['completados']).' ('.round($s['pct_completado']).' %)'],
            ['Todavía no empiezan', number_format($s['sin_iniciar'])],
            ['Calificación promedio', $s['calif_promedio'].' ('.round($s['pct_80']).' % sacó 80 o más)'],
            ['Satisfacción', round($s['satisfaccion']).' % de '.number_format($s['respuestas']).' respuestas'],
            ['Filas cubo / inicio_mes / encuesta', count($data['cubo']['rows']).' / '.count($data['inicio_mes']['rows']).' / '.count($data['encuesta']['rows'])],
            ['Pendientes de clasificar', count($data['pending'])],
            ['Tiempo', round(microtime(true) - $start, 1).' s'],
        ]);

        foreach ($data['warnings'] as $warning) {
            $this->line(' - '.$warning);
        }

        if (! $this->option('expected')) {
            return self::SUCCESS;
        }

        $expected = $this->expected($this->option('expected'));

        if ($expected === null) {
            $this->error('El HTML esperado no tiene el bloque window.__DATA.');

            return self::FAILURE;
        }

        $differences = 0;
        foreach (['cubo', 'inicio_mes', 'encuesta', 'catalogo'] as $set) {
            $differences += $this->compare($set, $expected[$set], $data[$set]);
        }

        if ($differences === 0) {
            $this->info('✔ Coincide exactamente con el script de referencia (4 conjuntos, fila por fila).');

            return self::SUCCESS;
        }

        $this->error("✘ {$differences} diferencias con el script de referencia.");

        return self::FAILURE;
    }

    /** @return array<string, array{columns: list<string>, rows: list<list<string>>}>|null */
    private function expected(string $file): ?array
    {
        if (! preg_match('/window\.__DATA=(\{.*?\});(?=\s*<\/script>)/s', (string) @file_get_contents($file), $m)) {
            return null;
        }

        $sets = [];
        foreach (json_decode($m[1], true) as $name => $csv) {
            $lines = array_map('str_getcsv', preg_split('/\R/', trim($csv)));
            $sets[$name] = ['columns' => array_shift($lines), 'rows' => $lines];
        }

        return $sets;
    }

    private function compare(string $set, array $expected, array $actual): int
    {
        $differences = 0;

        if ($expected['columns'] !== $actual['columns']) {
            $this->warn("[{$set}] columnas distintas:\n  esperado: ".implode(',', $expected['columns'])."\n  obtenido: ".implode(',', $actual['columns']));
            $differences++;
        }

        if (count($expected['rows']) !== count($actual['rows'])) {
            $this->warn("[{$set}] filas: esperado ".count($expected['rows']).', obtenido '.count($actual['rows']));
            $differences++;
        }

        foreach ($expected['rows'] as $i => $row) {
            $got = array_map(fn ($v) => is_float($v) ? self::pyFloat($v) : (string) $v, $actual['rows'][$i] ?? []);

            if ($row !== $got) {
                if ($differences < 15) {
                    $this->warn("[{$set}] fila ".($i + 1).":\n  esperado: ".implode(',', $row)."\n  obtenido: ".implode(',', $got));
                }
                $differences++;
            }
        }

        $this->line(sprintf('[%s] %d filas comparadas', $set, count($expected['rows'])));

        return $differences;
    }

    /** Como str(float) de Python: 1234.0, 12.5 */
    private static function pyFloat(float $value): string
    {
        $text = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');

        return str_contains($text, '.') ? $text : $text.'.0';
    }
}
