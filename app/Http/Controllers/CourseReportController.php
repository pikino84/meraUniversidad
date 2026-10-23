<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reports\CourseReportRequest;
use App\Models\CourseGroup;
use App\Models\CourseReport;
use App\Services\Reports\CourseReportManager;
use App\Services\Reports\ReportFormatException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Reportes de curso por carpeta (docs/reportes/INSTRUCTIVO_REPORTES.md §5.3).
 * Panel: super admin y admin. El link para compartir (shared) es público con token.
 */
class CourseReportController extends Controller
{
    public function __construct(private readonly CourseReportManager $reports) {}

    public function create(CourseGroup $courseGroup): View
    {
        Gate::authorize('create', CourseReport::class);

        return view('reports.create', [
            'group' => $courseGroup,
            'surveys' => $this->surveys(),
            'versions' => $this->versions($courseGroup),
        ]);
    }

    public function store(CourseReportRequest $request, CourseGroup $courseGroup): RedirectResponse
    {
        try {
            $report = $this->reports->create(
                $courseGroup,
                $request->string('title')->trim(),
                $request->input('cut_date'),
                $request->input('survey'),
                $request->workbooks(),
                $request->user()->id,
                $request->integer('course_group_version_id') ?: null,
            );
        } catch (ReportFormatException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $this->unexpected($e, 'Error al crear un corte de reporte', ['group' => $courseGroup->id]));
        }

        return redirect()->route('course-groups.reports.show', [$courseGroup, $report])
            ->with('success', 'Corte creado con la encuesta «'.config("report_surveys.{$report->survey}.name", $report->survey).'».'
                .($report->warnings ? ' Revisa los avisos.' : ''));
    }

    public function show(CourseGroup $courseGroup, CourseReport $report): View
    {
        Gate::authorize('view', $report);

        return view('reports.show', ['group' => $courseGroup, 'report' => $report, 'shared' => false]);
    }

    public function edit(CourseGroup $courseGroup, CourseReport $report): View
    {
        Gate::authorize('update', $report);

        return view('reports.edit', ['group' => $courseGroup, 'report' => $report, 'versions' => $this->versions($courseGroup)]);
    }

    public function update(CourseReportRequest $request, CourseGroup $courseGroup, CourseReport $report): RedirectResponse
    {
        $files = $request->workbooks();

        try {
            $this->reports->update($report, $request->string('title')->trim(), $request->input('cut_date'), $files, $request->user()->id, $request->integer('course_group_version_id') ?: null);
        } catch (ReportFormatException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $this->unexpected($e, 'Error al actualizar un corte de reporte', ['report' => $report->id]));
        }

        return redirect()->route('course-groups.reports.show', [$courseGroup, $report])
            ->with('success', $files ? 'Corte recalculado con los archivos nuevos.' : 'Corte actualizado.');
    }

    public function destroy(CourseGroup $courseGroup, CourseReport $report): RedirectResponse
    {
        Gate::authorize('delete', $report);

        $report->delete();

        $back = $report->course_group_version_id
            ? route('course-groups.versions.show', [$courseGroup, $report->course_group_version_id, 'seccion' => 'reportes'])
            : route('course-groups.show', $courseGroup);

        return redirect($back)->with('success', "Corte «{$report->title}» eliminado.");
    }

    /**
     * "Descargar HTML": el tablero en un solo archivo autocontenido (estilos, d3 y datos
     * incrustados) para compartir por correo. Solo agregados, igual que la vista del panel.
     */
    public function download(CourseGroup $courseGroup, CourseReport $report): Response
    {
        Gate::authorize('view', $report);

        $html = view('reports.standalone', [
            'group' => $courseGroup,
            'report' => $report,
            'css' => Vite::content('resources/css/report.css'),
            'js' => Vite::content('resources/js/report-dashboard.js'),
        ])->render();

        $name = Str::slug($courseGroup->name.' '.$report->cut_date->toDateString()) ?: 'reporte';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"reporte-{$name}.html\"",
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** Activar / regenerar / desactivar el link para compartir sin sesión. */
    public function share(Request $request, CourseGroup $courseGroup, CourseReport $report): RedirectResponse
    {
        Gate::authorize('update', $report);

        $action = $request->validate(['action' => ['required', 'in:enable,regenerate,disable']])['action'];
        $this->reports->share($report, $action !== 'disable');

        return back()->with('success', match ($action) {
            'enable' => 'Link para compartir activado.',
            'regenerate' => 'Link nuevo generado: el anterior ya no funciona.',
            default => 'Link para compartir desactivado.',
        });
    }

    /** Comentarios abiertos pendientes de clasificar (propuesta por palabras clave). */
    public function pending(CourseGroup $courseGroup, CourseReport $report): View
    {
        Gate::authorize('update', $report);

        return view('reports.pending', [
            'group' => $courseGroup,
            'report' => $report,
            'catalog' => config("report_surveys.{$report->survey}.catalog", []),
            'proposed' => $this->proposed($report),
        ]);
    }

    public function classify(Request $request, CourseGroup $courseGroup, CourseReport $report): RedirectResponse
    {
        Gate::authorize('update', $report);

        $data = $request->validate([
            'codes' => ['array'],
            'codes.*' => ['array'],
            'codes.*.*' => ['string', 'max:20'],
            'reviewed' => ['required', 'array', 'min:1'],
            'reviewed.*' => ['integer', 'min:0'],
        ], ['reviewed.required' => 'Marca al menos un comentario como revisado.']);

        $assignments = [];
        foreach ($data['reviewed'] as $index) {
            $assignments[(int) $index] = $data['codes'][$index] ?? [];
        }

        $done = $this->reports->classify($report, $assignments);

        return redirect()->route('course-groups.reports.pending', [$courseGroup, $report])
            ->with('success', "{$done} ".($done === 1 ? 'comentario clasificado.' : 'comentarios clasificados.'));
    }

    /** Vista pública con el link para compartir (sin sesión, noindex, solo agregados). */
    public function shared(string $token): Response
    {
        $report = CourseReport::with('group')->where('share_token', $token)->firstOrFail();

        return response()
            ->view('reports.show', ['group' => $report->group, 'report' => $report, 'shared' => true])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer'); // que el token no viaje en el Referer
    }

    private function versions(CourseGroup $group)
    {
        return $group->versions()->with('market')->ordered()->get();
    }

    /**
     * Error inesperado (no de formato): se registra con un código corto que se muestra en pantalla,
     * para que Capacitación lo pase y se encuentre el detalle en storage/logs (pedido 2026-10-22).
     */
    private function unexpected(Throwable $e, string $what, array $context): string
    {
        $code = strtoupper(Str::random(6));
        Log::error($what, $context + ['codigo' => $code, 'exception' => $e]);

        return 'No se pudo procesar el reporte por un error inesperado (no es de formato de los archivos). '
            ."Inténtalo de nuevo; si se repite, comparte este código con soporte: {$code}.";
    }

    /** @return array<string,string> clave => nombre ("Automática" primero: elige por el curso de la versión) */
    private function surveys(): array
    {
        return [CourseReportManager::AUTO_SURVEY => 'Automática (según el curso)']
            + collect(config('report_surveys'))->map(fn ($s, $key) => $s['name'] ?? $key)->all();
    }

    /** Códigos propuestos (los que hoy tiene la fila) para cada comentario pendiente. */
    private function proposed(CourseReport $report): array
    {
        $columns = $report->data['encuesta']['columns'];
        $rows = $report->data['encuesta']['rows'];

        return array_map(function ($item) use ($columns, $rows) {
            $row = array_combine($columns, $rows[$item['row']] ?? array_fill(0, count($columns), 0));

            return array_keys(array_filter($row, fn ($v, $k) => $v === 1 && preg_match('/^(m\d+|v\d+|v_todo)$/', $k), ARRAY_FILTER_USE_BOTH));
        }, $report->pending ?? []);
    }
}
