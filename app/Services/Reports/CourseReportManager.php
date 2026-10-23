<?php

namespace App\Services\Reports;

use App\Models\CourseGroup;
use App\Models\CourseGroupVersion;
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
    /** Valor del selector "Encuesta" que elige la configuración sola (surveyFor). */
    public const AUTO_SURVEY = 'auto';

    public function __construct(private readonly CourseReportBuilder $builder) {}

    /**
     * Configuración de encuesta para un corte: la del curso de la versión (course_codes), luego la
     * del título de la columna "Cuestionario" (title) y, si ninguna coincide, "general".
     */
    public static function surveyFor(?string $courseCode, ?string $title): string
    {
        $surveys = (array) config('report_surveys');

        foreach ($surveys as $key => $survey) {
            if ($courseCode && in_array($courseCode, $survey['course_codes'] ?? [], true)) {
                return $key;
            }
        }

        $plain = ReportWorkbookReader::normalize(ReportWorkbookReader::clean($title));
        foreach ($surveys as $key => $survey) {
            if ($plain !== '' && ! empty($survey['title']) && str_contains($plain, ReportWorkbookReader::normalize($survey['title']))) {
                return $key;
            }
        }

        return array_key_exists('general', $surveys) ? 'general' : (string) array_key_first($surveys);
    }

    /**
     * @param  array{personal: UploadedFile, inscripciones: UploadedFile, encuesta: UploadedFile}  $files
     *
     * @throws ReportFormatException
     */
    public function create(CourseGroup $group, string $title, string $cutDate, string $surveyKey, array $files, ?int $userId, ?int $versionId = null): CourseReport
    {
        $courseCode = $versionId ? CourseGroupVersion::query()->whereKey($versionId)->value('catalog_code') : null;
        $data = $this->buildFrom($files, $surveyKey, $courseCode);
        $surveyKey = $data['survey_key'];

        return DB::transaction(function () use ($group, $title, $cutDate, $surveyKey, $data, $userId, $versionId) {
            $report = new CourseReport(['title' => $title, 'cut_date' => $cutDate, 'survey' => $surveyKey]);
            $report->group()->associate($group);
            $report->course_group_version_id = $versionId;
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
    public function update(CourseReport $report, string $title, string $cutDate, ?array $files, ?int $userId, ?int $versionId = null): CourseReport
    {
        $data = $files ? $this->buildFrom($files, $report->survey) : null;

        DB::transaction(function () use ($report, $title, $cutDate, $data, $userId, $versionId) {
            $report->fill(['title' => $title, 'cut_date' => $cutDate]);

            if ($versionId) {
                $report->course_group_version_id = $versionId;
            }

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

        // Los que no dicen nada ("", ".", "N/A") salen de la lista: no hay qué clasificar.
        $pending = array_values(array_filter($pending, fn ($p) => SurveyAnswerClassifier::meaningful($p['liked'] ?? '')
            || SurveyAnswerClassifier::meaningful($p['improve'] ?? '', true)));

        $report->forceFill(['data' => $data, 'pending' => $pending ?: null]);
        $report->forceFill(['summary' => ['pendientes' => count($pending), 'sin_tema' => count($report->reviewQueue()['sin-tema'])] + ($report->summary ?? [])])->save();

        return $done;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFrom(array $files, string $surveyKey, ?string $courseCode = null): array
    {
        if ($surveyKey !== self::AUTO_SURVEY && ! config("report_surveys.{$surveyKey}")) {
            throw new ReportFormatException('La encuesta configurada para este reporte no existe.');
        }

        $dir = rtrim(config('mera.reports.temp_root'), '/\\').DIRECTORY_SEPARATOR.Str::uuid();
        File::ensureDirectoryExists($dir, 0700);

        // Nombre de cada archivo tal como lo subieron, para decir en el error cuál falla.
        $names = [];
        foreach (['personal' => 'reporte de personal', 'inscripciones' => 'reporte de inscripciones', 'encuesta' => 'encuesta de satisfacción'] as $key => $label) {
            $names[$label] = $files[$key] instanceof UploadedFile ? $files[$key]->getClientOriginalName() : basename((string) $files[$key]);
        }

        try {
            $paths = [];
            foreach (['personal', 'inscripciones', 'encuesta'] as $key) {
                $paths[$key] = $files[$key]->move($dir, "{$key}.xlsx")->getPathname();
            }

            if ($surveyKey === self::AUTO_SURVEY) {
                $surveyKey = self::surveyFor($courseCode, $this->builder->surveyTitle($paths['encuesta']));
            }

            return $this->builder->build($paths['personal'], $paths['inscripciones'], $paths['encuesta'], config("report_surveys.{$surveyKey}"))
                + ['survey_key' => $surveyKey];
        } catch (ReportFormatException $e) {
            if (! isset($names[$e->workbook])) {
                throw $e;
            }
            // Sin encadenar el original: su traza retiene el lector y el Excel quedaría abierto (en
            // Windows no se borraría la carpeta temporal en el finally).
            $error = $e->withUploadedName($names[$e->workbook]);
            unset($e);

            throw $error;
        } finally {
            // openspout solo cierra el XML de la hoja al recorrerla completa: si un error corta la
            // lectura (p. ej. falta una columna), el archivo sigue abierto hasta que se liberan sus
            // objetos, y en Windows un archivo abierto no se puede borrar.
            gc_collect_cycles();

            // En Windows el archivo puede seguir tomado unos milisegundos (antivirus, indexador): reintenta.
            for ($try = 0; $try < 3 && is_dir($dir) && ! File::deleteDirectory($dir); $try++) {
                usleep(150_000);
            }

            if (is_dir($dir)) {
                Log::warning('No se pudo borrar la carpeta temporal de un reporte (trae datos personales): bórrala a mano.', ['dir' => $dir]);
            }
        }
    }

    private function fill(CourseReport $report, array $data): void
    {
        $report->forceFill([
            'data' => array_intersect_key($data, array_flip(['cubo', 'inicio_mes', 'encuesta', 'catalogo', 'preguntas'])),
            'warnings' => $data['warnings'] ?: null,
            'pending' => $data['pending'] ?: null,
        ]);
        // sin_tema: lo que de verdad pide revisión (botón "Revisar comentarios" y lista de cortes).
        $report->forceFill(['summary' => $data['summary'] + [
            'pendientes' => count($data['pending']),
            'sin_tema' => count($report->reviewQueue()['sin-tema']),
        ]]);
    }
}
