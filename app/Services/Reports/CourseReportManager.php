<?php

namespace App\Services\Reports;

use App\Models\CourseGroup;
use App\Models\CourseReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Casos de uso de los cortes de reporte (INSTRUCTIVO_REPORTES.md §5.3).
 *
 * Los 3 Excel se guardan en una carpeta temporal PRIVADA (storage/app/tmp/report-uploads/<uuid>)
 * y se borran SIEMPRE al terminar (finally), con éxito o con error. No se guarda ningún Excel ni
 * ningún dato personal: solo lo que devuelve CourseReportBuilder.
 */
class CourseReportManager
{
    public function __construct(private readonly CourseReportBuilder $builder) {}

    /**
     * @param  array{personal: UploadedFile, inscripciones: UploadedFile, encuesta: UploadedFile}  $files
     *
     * @throws ReportFormatException
     */
    public function create(CourseGroup $group, string $title, string $cutDate, string $surveyKey, array $files, ?int $userId): CourseReport
    {
        $data = $this->buildFrom($files, $surveyKey);

        return DB::transaction(function () use ($group, $title, $cutDate, $surveyKey, $data, $userId) {
            $report = new CourseReport(['title' => $title, 'cut_date' => $cutDate, 'survey' => $surveyKey]);
            $report->group()->associate($group);
            $this->fill($report, $data);
            $report->forceFill(['created_by' => $userId, 'updated_by' => $userId])->save();

            return $report;
        });
    }

    /**
     * Editar título y fecha y, si vienen los 3 Excel, recalcular el MISMO corte (decisión del
     * usuario, §10). Recalcular reemplaza los datos y las clasificaciones pendientes.
     *
     * @param  array{personal: UploadedFile, inscripciones: UploadedFile, encuesta: UploadedFile}|null  $files
     *
     * @throws ReportFormatException
     */
    public function update(CourseReport $report, string $title, string $cutDate, ?array $files, ?int $userId): CourseReport
    {
        $data = $files ? $this->buildFrom($files, $report->survey) : null;

        DB::transaction(function () use ($report, $title, $cutDate, $data, $userId) {
            $report->fill(['title' => $title, 'cut_date' => $cutDate]);

            if ($data !== null) {
                $this->fill($report, $data);
            }

            $report->forceFill(['updated_by' => $userId])->save();
        });

        return $report;
    }

    /**
     * Link para compartir sin sesión: activar (o regenerar, que invalida el anterior) o desactivar.
     */
    public function share(CourseReport $report, bool $enable): CourseReport
    {
        $report->forceFill($enable
            ? ['share_token' => Str::random(48), 'shared_at' => now()]
            : ['share_token' => null, 'shared_at' => null])->save();

        return $report;
    }

    /**
     * Guarda la clasificación manual de comentarios pendientes y los quita de la lista.
     *
     * @param  array<int, list<string>>  $assignments  índice en `pending` => códigos marcados
     */
    public function classify(CourseReport $report, array $assignments): int
    {
        $data = $report->data;
        $pending = $report->pending ?? [];
        $columns = $data['encuesta']['columns'];
        // Solo los temas: v_respondio (¿la persona escribió algo?) no se elige a mano.
        $codes = array_values(array_filter($columns, fn ($c) => preg_match('/^(m\d+|v\d+|v_todo)$/', $c)));
        $done = 0;

        foreach ($assignments as $index => $marked) {
            $item = $pending[$index] ?? null;

            if (! $item || ! isset($data['encuesta']['rows'][$item['row']])) {
                continue;
            }

            foreach ($codes as $code) {
                $col = array_search($code, $columns, true);
                $data['encuesta']['rows'][$item['row']][$col] = (int) in_array($code, $marked, true);
            }

            unset($pending[$index]);
            $done++;
        }

        $summary = $report->summary ?? [];
        $summary['pendientes'] = count($pending);

        $report->forceFill(['data' => $data, 'pending' => array_values($pending) ?: null, 'summary' => $summary])->save();

        return $done;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFrom(array $files, string $surveyKey): array
    {
        $survey = config("report_surveys.{$surveyKey}");

        if (! $survey) {
            throw new ReportFormatException('La encuesta configurada para este reporte no existe.');
        }

        $dir = rtrim(config('mera.reports.temp_root'), '/\\').DIRECTORY_SEPARATOR.Str::uuid();
        File::ensureDirectoryExists($dir, 0700);

        try {
            $paths = [];
            foreach (['personal', 'inscripciones', 'encuesta'] as $key) {
                $paths[$key] = $files[$key]->move($dir, "{$key}.xlsx")->getPathname();
            }

            return $this->builder->build($paths['personal'], $paths['inscripciones'], $paths['encuesta'], $survey);
        } finally {
            // openspout solo cierra el XML de la hoja al recorrerla completa: si un error corta la
            // lectura (p. ej. falta una columna), el archivo sigue abierto hasta que se liberan sus
            // objetos, y en Windows un archivo abierto no se puede borrar.
            gc_collect_cycles();

            if (! File::deleteDirectory($dir) && is_dir($dir)) {
                Log::warning('No se pudo borrar la carpeta temporal de un reporte (trae datos personales): bórrala a mano.', ['dir' => $dir]);
            }
        }
    }

    private function fill(CourseReport $report, array $data): void
    {
        $report->forceFill([
            'data' => array_intersect_key($data, array_flip(['cubo', 'inicio_mes', 'encuesta', 'catalogo'])),
            'summary' => $data['summary'] + ['pendientes' => count($data['pending'])],
            'warnings' => $data['warnings'] ?: null,
            'pending' => $data['pending'] ?: null,
        ]);
    }
}
