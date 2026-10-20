<?php

namespace App\Services\Reports;

use App\Models\ProgressReport;
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

    public function __construct(private readonly ProgressReportBuilder $builder) {}

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
     * @param  callable(array<string,string>): T  $callback
     * @return T
     */
    private function withTemporaryFiles(array $files, callable $callback): mixed
    {
        $dir = rtrim(config('mera.reports.temp_root'), '/\\').DIRECTORY_SEPARATOR.Str::uuid();
        File::ensureDirectoryExists($dir, 0700);

        try {
            $paths = [];
            foreach (array_keys(self::FILES) as $key) {
                $paths[$key] = $files[$key] instanceof UploadedFile
                    ? $files[$key]->move($dir, "{$key}.xlsx")->getPathname()
                    : (string) $files[$key];
            }

            return $callback($paths);
        } finally {
            gc_collect_cycles(); // openspout en Windows: suelta el archivo antes de borrarlo

            if (! File::deleteDirectory($dir) && is_dir($dir)) {
                Log::warning('No se pudo borrar la carpeta temporal del seguimiento (trae datos personales): bórrala a mano.', ['dir' => $dir]);
            }
        }
    }
}
