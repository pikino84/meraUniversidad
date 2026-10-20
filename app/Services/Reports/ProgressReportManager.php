<?php

namespace App\Services\Reports;

use App\Models\CatalogCourse;
use App\Models\ProgressReport;
use App\Models\ProgressReportSurvey;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Crea y borra cortes del seguimiento anual (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §6 y §9).
 *
 * Los 2 Excel (progreso y personal) se mueven a una carpeta temporal PRIVADA y se borran SIEMPRE
 * (finally), como en el reporte por curso; mera:purge-report-uploads limpia lo que quede si PHP se
 * corta. Se conservan los últimos config('mera.progress.keep_reports') cortes.
 */
class ProgressReportManager
{
    public const FILES = [
        'progreso' => 'reporte de progreso de capacitaciones',
        'personal' => 'reporte de personal',
    ];

    public function __construct(
        private readonly ProgressReportBuilder $builder,
        private readonly ProgressSurveyAnalyzer $surveys,
    ) {}

    /**
     * @param  array{progreso: UploadedFile|string, personal: UploadedFile|string}  $files  subidos o rutas
     */
    public function create(string $title, string $cutDate, array $files, ?int $userId): ProgressReport
    {
        @set_time_limit(300);

        $data = $this->withTemporaryFiles($files, fn (array $paths) => $this->builder->build($paths['progreso'], $paths['personal']));

        $report = DB::transaction(function () use ($title, $cutDate, $data, $userId) {
            $report = ProgressReport::create([
                'title' => $title,
                'cut_date' => $cutDate,
                'summary' => $data['summary'],
                'warnings' => $data['warnings'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach (array_chunk(array_values($data['people']), 500) as $chunk) {
                DB::table('progress_report_people')->insert(array_map(fn ($p) => $p + ['progress_report_id' => $report->id], $chunk));
            }

            $ids = DB::table('progress_report_people')->where('progress_report_id', $report->id)->pluck('id', 'employee_number');

            foreach (array_chunk($data['items'], 1000) as $chunk) {
                DB::table('progress_report_items')->insert(array_map(function ($item) use ($report, $ids) {
                    $item['progress_report_person_id'] = $ids[$item['employee_number']];
                    $item['progress_report_id'] = $report->id;
                    unset($item['employee_number']);

                    return $item;
                }, $chunk));
            }

            return $report;
        });

        $this->prune();

        return $report;
    }

    /**
     * Agrega (o reemplaza) la encuesta de un curso al corte (§6.1). El curso se toma del selector o, si
     * no se eligió, se propone por el título del cuestionario entre los cursos del corte.
     *
     * @throws ReportFormatException
     */
    public function addSurvey(ProgressReport $report, UploadedFile|string $file, ?string $courseCode, ?int $userId): ProgressReportSurvey
    {
        $candidates = $this->courseCandidates($report);

        return $this->withTemporaryFiles(['encuesta' => $file], function (array $paths) use ($report, $courseCode, $userId, $candidates) {
            $result = $this->surveys->analyze($paths['encuesta'], $courseCode);
            $code = $courseCode ?: ProgressSurveyAnalyzer::guessCourse($result['title'], $candidates);

            if ($code === null) {
                throw new ReportFormatException("No se reconoció el curso de «{$result['title']}». Elige el curso en la lista y vuelve a subirla.");
            }

            // Curso propuesto con configuración en report_surveys: se vuelve a leer para su satisfacción.
            if ($code !== $courseCode && collect(config('report_surveys'))->contains(fn ($s) => in_array($code, $s['course_codes'] ?? [], true))) {
                $result = $this->surveys->analyze($paths['encuesta'], $code);
            }

            $inCut = DB::table('progress_report_items as i')->join('progress_report_people as p', 'p.id', '=', 'i.progress_report_person_id')
                ->where('i.progress_report_id', $report->id)->where('i.course_code', $code)->pluck('p.employee_number')->flip();
            $notInCut = count(array_filter($result['employees'], fn ($num) => ! isset($inCut[$num])));

            $warnings = $result['warnings'];
            if ($notInCut) {
                $warnings[] = "{$notInCut} respuestas son de personas que no tienen este curso en el corte (bajas, otra localidad o el curso no se les asignó).";
            }

            return DB::transaction(function () use ($report, $code, $result, $notInCut, $warnings, $userId) {
                $report->surveys()->where('course_code', $code)->delete();

                return $report->surveys()->create([
                    'catalog_course_id' => CatalogCourse::resolve($code)?->id,
                    'course_code' => $code,
                    'title' => mb_substr($result['title'], 0, 255),
                    'responses' => $result['responses'],
                    'active' => $result['active'],
                    'not_in_cut' => $notInCut,
                    'data' => $result['data'],
                    'warnings' => $warnings ?: null,
                    'created_by' => $userId,
                ]);
            });
        });
    }

    /**
     * Guarda los temas revisados a mano de los comentarios mostrados (índices de data.topics.answers) y los
     * marca como revisados. Solo acepta temas del catálogo de la encuesta.
     *
     * @param  list<int>  $shown
     * @param  array<int, list<string>>  $topics
     */
    public function classifySurvey(ProgressReportSurvey $survey, array $shown, array $topics): int
    {
        $data = $survey->data;
        $valid = array_column($data['topics']['catalog'], 0);
        $saved = 0;

        foreach ($shown as $index) {
            if (! isset($data['topics']['answers'][$index])) {
                continue;
            }

            $data['topics']['answers'][$index]['c'] = array_values(array_intersect($valid, (array) ($topics[$index] ?? [])));
            $data['topics']['answers'][$index]['r'] = true;
            $saved++;
        }

        $survey->forceFill(['data' => $data])->save();

        return $saved;
    }

    /**
     * Cursos del corte para proponer el de una encuesta: código => título y nombre corto.
     *
     * @return array<string,string>
     */
    public function courseCandidates(ProgressReport $report): array
    {
        $codes = DB::table('progress_report_items')->where('progress_report_id', $report->id)
            ->where('year', '>=', (int) config('mera.progress.tracking_from_year', 2026))->distinct()->pluck('course_code');
        $catalog = CatalogCourse::query()->whereIn('code', $codes)->get()->keyBy('code');

        return $codes->mapWithKeys(fn ($code) => [$code => trim(($catalog[$code]->title ?? '').' '.($catalog[$code]->short_title ?? $code))])->all();
    }

    public function delete(ProgressReport $report): void
    {
        DB::transaction(function () use ($report) {
            // En lotes: con ~75 mil filas, el borrado en cascada de una sola vez es pesado.
            DB::table('progress_report_items')->where('progress_report_id', $report->id)->orderBy('id')->chunkById(5000, function ($rows) {
                DB::table('progress_report_items')->whereIn('id', $rows->pluck('id'))->delete();
            });
            $report->delete();
        });
    }

    /** Conserva los cortes más recientes (por fecha de corte y de creación). */
    private function prune(): void
    {
        $keep = max(1, (int) config('mera.progress.keep_reports', 6));

        ProgressReport::query()->orderByDesc('cut_date')->orderByDesc('id')->skip($keep)->take(PHP_INT_MAX)->get()
            ->each(fn (ProgressReport $old) => $this->delete($old));
    }

    /**
     * @template T
     *
     * @param  array<string, UploadedFile|string>  $files
     * @param  callable(array<string,string>): T  $callback
     * @return T
     */
    private function withTemporaryFiles(array $files, callable $callback): mixed
    {
        $dir = rtrim(config('mera.reports.temp_root'), '/\\').DIRECTORY_SEPARATOR.Str::uuid();
        File::ensureDirectoryExists($dir, 0700);

        try {
            $paths = [];
            foreach (array_keys($files) as $key) {
                $paths[$key] = $files[$key] instanceof UploadedFile
                    ? $files[$key]->move($dir, "{$key}.xlsx")->getPathname()
                    : (string) $files[$key];
            }

            return $callback($paths);
        } finally {
            gc_collect_cycles(); // openspout en Windows: suelta el archivo antes de borrarlo

            // En Windows el archivo puede seguir tomado unos milisegundos (antivirus, indexador): reintenta.
            for ($try = 0; $try < 3 && is_dir($dir) && ! File::deleteDirectory($dir); $try++) {
                usleep(150_000);
            }

            if (is_dir($dir)) {
                Log::warning('No se pudo borrar la carpeta temporal del seguimiento (trae datos personales): bórrala a mano.', ['dir' => $dir]);
            }
        }
    }
}
