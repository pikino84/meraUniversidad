<?php

namespace App\Http\Controllers;

use App\Models\ProgressReport;
use App\Models\ProgressReportItem;
use App\Models\ProgressReportPerson;
use App\Models\ProgressReportShare;
use App\Models\ProgressReportSurvey;
use App\Services\Reports\ProgressCourseSummary;
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

    /** Pestañas del tablero (?vista=): una a la vez, cada una calcula solo lo que muestra. */
    public const VIEWS = [
        'resumen' => 'Resumen',
        'cursos' => 'Cursos',
        'localidades' => 'Localidades',
        'areas' => 'Áreas y puestos',
        'evaluacion' => 'Evaluación',
        'opiniones' => 'Opiniones',
    ];

    public function show(Request $request, ProgressReport $report): View|RedirectResponse
    {
        // Link anterior con número (/seguimiento/1): al link con la fecha, con los mismos filtros.
        if ($request->route()->originalParameter('report') !== $report->slug) {
            return redirect()->to(route('progress.show', $report).($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
        }

        return view('progress.show', $this->dashboardData($request, $report, null) + [
            'shares' => $report->shares()->count(),
        ]);
    }

    /**
     * Link para compartir sin sesión (§7.2.7): mismo tablero, fijo en su localidad (si tiene), sin nombres,
     * sin consulta de colaboradores ni respuestas abiertas, y sin comparar localidades. noindex.
     */
    public function shared(Request $request, string $token)
    {
        $share = ProgressReportShare::with('report')->where('token', $token)->firstOrFail();

        if ($share->locality !== null) {
            $request->query->set('localidad', $share->locality);
        }

        return response()
            ->view('progress.show', $this->dashboardData($request, $share->report, $share))
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    /** Datos del tablero para el panel ($share = null) o para un link compartido. */
    private function dashboardData(Request $request, ProgressReport $report, ?ProgressReportShare $share): array
    {
        $dashboard = new ProgressDashboard($report, $request);
        $views = self::VIEWS;

        // Compartido por localidad: sin comparar localidades. Compartido en general: sí, pero sin nombres.
        if ($share?->locality !== null) {
            unset($views['localidades']);
        }

        $view = array_key_exists($request->query('vista'), $views) ? $request->query('vista') : 'resumen';
        $sort = in_array($request->query('orden'), ['avance-asc', 'avance-desc', 'nombre'], true) ? $request->query('orden') : 'avance-asc';
        $route = $share ? fn (array $query) => route('progress.shared', ['token' => $share->token] + $query) : fn (array $query) => route('progress.show', ['report' => $report] + $query);

        $data = [
            'report' => $report,
            'd' => $dashboard,
            'share' => $share,
            'url' => $route,
            'filters' => $dashboard->filters,
            'options' => $dashboard->options(),
            'totals' => $dashboard->totals(),
            'comparable' => $dashboard->comparableTotals(),
            'view' => $view,
            'views' => $views,
            'sort' => $sort,
            // Con un curso elegido: sus versiones de carpeta (y desde ahí, sus cortes por curso).
            'courseVersions' => ! $share && $dashboard->filters['curso'] ? ProgressCourseSummary::versionsFor($dashboard->filters['curso']) : collect(),
        ];

        $minAssigned = (int) config('mera.progress.dashboard.attention_min_assigned', 20);

        return $data + match ($view) {
            'resumen' => [
                'courses' => $dashboard->byCourse(),
                'attentionCourses' => $dashboard->byCourse()->filter(fn ($c) => $c['assigned'] >= $minAssigned)->sortBy('pct_done')->take(5)->values(),
                'attentionLocalities' => $share?->locality !== null ? collect() : $dashboard->byLocality()->sortBy('pct_done')->take(5)->values(),
                'months' => $dashboard->byMonth(),
            ],
            'cursos' => [
                'courses' => $this->sortRows($dashboard->byCourse(), $sort),
                'specific' => $dashboard->specificCourses(),
            ],
            'localidades' => [
                'localities' => $dashboard->byLocality(),
                'failing' => $dashboard->failingByLocality(),
            ],
            'areas' => [
                'areas' => $dashboard->byArea(),
                'positions' => $dashboard->byPosition()->sortBy('pct_done')->take(15)->values(),
            ],
            'evaluacion' => [
                'bands' => $dashboard->gradeBands(),
                'failing' => $share?->locality !== null ? collect() : $dashboard->failingByLocality(),
                'courses' => $dashboard->byCourse()->filter(fn ($c) => $c['avg_grade'] !== null)->sortBy('avg_grade')->values(),
            ],
            'opiniones' => [
                // Encuestas (§6.1): la tabla compara cursos; con un curso elegido, su detalle.
                'surveys' => $report->surveys()->orderBy('course_code')->get(),
                'surveyCourses' => $share ? [] : $this->manager->courseCandidates($report),
            ],
        };
    }

    /** Links para compartir del corte (§7.2.7). */
    public function shares(ProgressReport $report): View
    {
        return view('progress.shares', [
            'report' => $report,
            'shares' => $report->shares()->orderByRaw('locality is not null')->orderBy('locality')->get(),
            'localities' => DB::table('progress_report_people')->where('progress_report_id', $report->id)->distinct()->orderBy('locality')->pluck('locality'),
        ]);
    }

    public function storeShare(Request $request, ProgressReport $report): RedirectResponse
    {
        $localities = DB::table('progress_report_people')->where('progress_report_id', $report->id)->distinct()->pluck('locality')->all();
        $data = $request->validate([
            'locality' => ['nullable', 'string', function ($attribute, $value, $fail) use ($localities) {
                if (filled($value) && ! in_array($value, $localities, true)) {
                    $fail('Esa localidad no está en este corte.');
                }
            }],
        ]);
        $locality = filled($data['locality'] ?? null) ? $data['locality'] : null;

        $share = $report->shares()->where('locality', $locality)->first()
            ?? $report->shares()->create(['locality' => $locality, 'created_by' => $request->user()->id]);

        activity('seguimiento')->causedBy($request->user())->performedOn($report)
            ->withProperties(['localidad' => $share->label()])->log('Creó un link para compartir el seguimiento');

        return redirect()->route('progress.shares', $report)->with('success', "Link de «{$share->label()}» listo: cópialo y compártelo.");
    }

    public function destroyShare(Request $request, ProgressReport $report, ProgressReportShare $share): RedirectResponse
    {
        abort_unless($share->progress_report_id === $report->id, 404);
        $share->delete();

        activity('seguimiento')->causedBy($request->user())->performedOn($report)
            ->withProperties(['localidad' => $share->label()])->log('Desactivó un link del seguimiento');

        return back()->with('success', "Link de «{$share->label()}» desactivado: ya no abre.");
    }

    private function sortRows($rows, string $sort)
    {
        return match ($sort) {
            'avance-desc' => $rows->sortByDesc('pct_done')->values(),
            'nombre' => $rows->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            default => $rows->sortBy('pct_done')->values(),
        };
    }

    /** Agrega (o reemplaza) la encuesta de satisfacción de un curso al corte. */
    public function storeSurvey(Request $request, ProgressReport $report): RedirectResponse
    {
        $request->validate([
            'encuesta' => ['required', 'file', 'max:'.(int) config('mera.reports.max_upload_kb'), function ($attribute, $value, $fail) {
                if ($value instanceof UploadedFile && strtolower($value->getClientOriginalExtension()) !== 'xlsx') {
                    $fail('La encuesta debe ser un archivo de Excel (.xlsx).');
                }
            }],
            'course_code' => ['nullable', 'string', 'max:40', function ($attribute, $value, $fail) use ($report) {
                if (filled($value) && ! array_key_exists($value, $this->manager->courseCandidates($report))) {
                    $fail('Ese curso no está en este corte.');
                }
            }],
        ], ['encuesta.required' => 'Elige el Excel de la encuesta.']);

        try {
            $survey = $this->manager->addSurvey($report, $request->file('encuesta'), $request->input('course_code') ?: null, $request->user()->id);
        } catch (ReportFormatException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Error al agregar una encuesta al seguimiento', ['report' => $report->id, 'exception' => $e]);

            return back()->with('error', 'No se pudo leer la encuesta. Revisa el archivo e inténtalo de nuevo.');
        }

        $labels = (new ProgressDashboard($report, $request))->courseLabels();

        return redirect()->route('progress.show', ['report' => $report, 'vista' => 'opiniones', 'curso' => $survey->course_code])
            ->with('success', 'Encuesta agregada a «'.($labels[$survey->course_code] ?? $survey->course_code).'»: '.number_format($survey->responses).' respuestas.'
                .($request->filled('course_code') ? '' : ' El curso se reconoció por el título; si no es, elimínala y vuelve a subirla eligiendo el curso.'));
    }

    public function destroySurvey(ProgressReport $report, ProgressReportSurvey $survey): RedirectResponse
    {
        abort_unless($survey->progress_report_id === $report->id, 404);
        $survey->delete();

        return back()->with('success', 'Encuesta eliminada del corte.');
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

    /** Nombre y fecha de corte (las cifras no cambian: para recalcular, se crea otro corte). */
    public function edit(ProgressReport $report): View
    {
        return view('progress.edit', ['report' => $report]);
    }

    public function update(Request $request, ProgressReport $report): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'cut_date' => ['required', 'date', 'before_or_equal:'.now(config('mera.display_timezone'))->toDateString()],
        ], [], ['title' => 'nombre', 'cut_date' => 'fecha de corte']);

        $report->update(['title' => trim($data['title']), 'cut_date' => $data['cut_date'], 'updated_by' => $request->user()->id]);

        return redirect()->route('progress.show', $report)->with('success', 'Corte actualizado.');
    }

    public function destroy(ProgressReport $report): RedirectResponse
    {
        $this->manager->delete($report);

        return redirect()->route('progress.index')->with('success', "Corte «{$report->title}» eliminado.");
    }
}
