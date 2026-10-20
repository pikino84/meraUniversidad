<?php

namespace App\Http\Controllers;

use App\Models\ProgressReport;
use App\Models\ProgressReportItem;
use App\Models\ProgressReportPerson;
use App\Services\Reports\ProgressDashboard;
use App\Services\Reports\ProgressReportManager;
use App\Services\Reports\ReportFormatException;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Seguimiento anual por persona (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md): todos los cursos que
 * cuentan del año, persona por persona. Panel: admin y super admin (trae nombres; §8).
 */
class ProgressReportController extends Controller
{
    public function __construct(private readonly ProgressReportManager $manager) {}

    public function index(): View
    {
        return view('progress.index', [
            'reports' => ProgressReport::with('creator:id,name')->orderByDesc('cut_date')->orderByDesc('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('progress.create');
    }

    public function store(Request $request): RedirectResponse
    {
        if (blank($request->input('cut_date'))) {
            $request->merge(['cut_date' => now(config('mera.display_timezone'))->toDateString()]);
        }

        $xlsx = function (string $attribute, mixed $value, Closure $fail) {
            if ($value instanceof UploadedFile && strtolower($value->getClientOriginalExtension()) !== 'xlsx') {
                $fail('El :attribute debe ser un archivo de Excel (.xlsx).');
            }
        };
        $maxKb = (int) config('mera.reports.max_upload_kb');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'cut_date' => ['required', 'date', 'before_or_equal:'.now(config('mera.display_timezone'))->toDateString()],
            'progreso' => ['required', 'file', "max:{$maxKb}", $xlsx],
            'personal' => ['required', 'file', "max:{$maxKb}", $xlsx],
        ], [], [
            'title' => 'título',
            'cut_date' => 'fecha de corte',
            'progreso' => 'reporte de progreso de capacitaciones',
            'personal' => 'reporte de personal',
        ]);

        try {
            $report = $this->manager->create(trim($data['title']), $data['cut_date'], [
                'progreso' => $request->file('progreso'),
                'personal' => $request->file('personal'),
            ], $request->user()->id);
        } catch (ReportFormatException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Error al crear un corte del seguimiento', ['exception' => $e]);

            return back()->withInput()->with('error', 'No se pudo procesar el corte. Revisa los archivos e inténtalo de nuevo.');
        }

        return redirect()->route('progress.show', $report)
            ->with('success', 'Corte creado.'.($report->warnings ? ' Revisa los avisos.' : ''));
    }

    public function show(Request $request, ProgressReport $report): View
    {
        $dashboard = new ProgressDashboard($report, $request);

        return view('progress.show', [
            'report' => $report,
            'd' => $dashboard,
            'filters' => $dashboard->filters,
            'options' => $dashboard->options(),
            'totals' => $dashboard->totals(),
            'courses' => $dashboard->byCourse(),
            'localities' => $dashboard->byLocality(),
            'areas' => $dashboard->byArea(),
            'months' => $dashboard->byMonth(),
            'bands' => $dashboard->gradeBands(),
            'failing' => $dashboard->failingByLocality(),
        ]);
    }

    /** Consulta de colaboradores: persona × curso por estado, con búsqueda (§7.2.5). */
    public function people(Request $request, ProgressReport $report): View
    {
        $dashboard = new ProgressDashboard($report, $request);
        $statusKey = array_key_exists($request->query('estado'), ProgressReportItem::STATUS_KEYS) ? $request->query('estado') : 'reprobados';

        return view('progress.people', [
            'report' => $report,
            'd' => $dashboard,
            'filters' => $dashboard->filters,
            'options' => $dashboard->options(),
            'statusKey' => $statusKey,
            'search' => trim((string) $request->query('q')),
            'rows' => $dashboard->people(ProgressReportItem::STATUS_KEYS[$statusKey], $request->query('q'))
                ->paginate((int) config('mera.progress.people_per_page', 25))->onEachSide(1)->withQueryString(),
            'counts' => $dashboard->statusCounts(),
        ]);
    }

    /** CSV de la consulta (con nombres): solo con sesión de administrador y queda en el historial. */
    public function export(Request $request, ProgressReport $report): StreamedResponse
    {
        $dashboard = new ProgressDashboard($report, $request);
        $statusKey = array_key_exists($request->query('estado'), ProgressReportItem::STATUS_KEYS) ? $request->query('estado') : 'reprobados';
        $labels = $dashboard->courseLabels();

        activity('seguimiento')->causedBy($request->user())->performedOn($report)
            ->withProperties(['estado' => $statusKey, 'filtros' => array_filter($dashboard->filters)])
            ->log('Descargó la lista de colaboradores (CSV)');

        $query = $dashboard->people(ProgressReportItem::STATUS_KEYS[$statusKey], $request->query('q'));
        $name = 'seguimiento-'.$statusKey.'-'.$report->cut_date->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $labels) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}"); // Excel abre bien los acentos
            fputcsv($out, ['No. empleado', 'Nombre', 'Localidad', 'Centro de trabajo', 'Área', 'Puesto', 'Código', 'Curso', 'Avance %', 'Calificación', 'Estado', 'Iniciado', 'Completado']);

            $query->chunk(1000, function ($rows) use ($out, $labels) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->employee_number, $r->full_name, $r->locality, $r->work_center, $r->area, $r->position,
                        $r->course_code, $labels[$r->course_code] ?? $r->course_code, round($r->progress * 100),
                        $r->grade, ProgressReportItem::STATUS_LABELS[$r->status] ?? '', $r->started_at, $r->completed_at,
                    ]);
                }
            });

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Ficha individual (§7.2.6): sus cursos del corte y su avance en cortes anteriores. */
    public function person(Request $request, ProgressReport $report, ProgressReportPerson $person): View
    {
        abort_unless($person->progress_report_id === $report->id, 404);

        $dashboard = new ProgressDashboard($report, $request);
        $from = (int) config('mera.progress.tracking_from_year', 2026);
        $items = $person->items()->with('catalogCourse')->where('year', '>=', $from)
            ->orderByDesc('year')->orderBy('status')->orderBy('course_code')->get()
            ->filter(fn ($i) => $i->catalogCourse?->in_tracking ?? true)->values();

        $history = DB::table('progress_report_people as p')
            ->join('progress_reports as r', 'r.id', '=', 'p.progress_report_id')
            ->join('progress_report_items as i', 'i.progress_report_person_id', '=', 'p.id')
            ->where('p.employee_number', $person->employee_number)->where('i.year', '>=', $from)
            ->groupBy('r.id', 'r.title', 'r.cut_date')
            ->selectRaw('r.id, r.title, r.cut_date, count(*) as assigned, sum(case when i.status in (1, 2) then 1 else 0 end) as done, sum(case when i.status = 1 then 1 else 0 end) as passed')
            ->orderByDesc('r.cut_date')->get();

        return view('progress.person', [
            'report' => $report,
            'person' => $person,
            'items' => $items,
            'labels' => $dashboard->courseLabels(),
            'history' => $history,
            'back' => url()->previous() !== url()->current() ? url()->previous() : route('progress.show', $report),
        ]);
    }

    public function destroy(ProgressReport $report): RedirectResponse
    {
        $this->manager->delete($report);

        return redirect()->route('progress.index')->with('success', "Corte «{$report->title}» eliminado.");
    }
}
