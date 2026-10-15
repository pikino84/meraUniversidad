{{-- Reportes de curso de la carpeta (docs/reportes/INSTRUCTIVO_REPORTES.md §5.3). Variables: $group, $reports --}}
@php $tz = config('mera.display_timezone'); @endphp
<section class="card mera-table-card mt-4" aria-labelledby="reportes-carpeta">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 id="reportes-carpeta" class="h5 mb-1 mera-section-title"><i class="fas fa-chart-bar" aria-hidden="true"></i> Reportes</h2>
                <small class="text-muted">Avance, evaluación y satisfacción por corte, a partir de los Excel de la plataforma de capacitación.</small>
            </div>
            <a href="{{ route('course-groups.reports.create', $group) }}" class="btn mera-btn-primary btn-sm">
                <i class="fas fa-plus" aria-hidden="true"></i> Nuevo corte
            </a>
        </div>

        @if ($reports->isEmpty())
        <div class="mera-empty-state">
            <i class="fas fa-chart-bar" aria-hidden="true"></i>
            <strong>Todavía no hay reportes</strong>
            <span>Sube los 3 Excel (personal, inscripciones y encuesta) para crear el primer corte.</span>
        </div>
        @else
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack table-hover mb-0">
                <thead>
                    <tr>
                        <th scope="col">Corte</th>
                        <th scope="col">Completaron</th>
                        <th scope="col">Promedio</th>
                        <th scope="col">Satisfacción</th>
                        <th scope="col" class="text-center mera-col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reports as $report)
                    @php $s = $report->summary ?? []; @endphp
                    <tr>
                        <td data-label="Corte">
                            <a href="{{ route('course-groups.reports.show', [$group, $report]) }}" class="user-name">{{ $report->cut_date->format('d/m/Y') }}</a>
                            <small class="d-block text-muted">{{ $report->title }}</small>
                            @if ($report->pendingCount())
                            <span class="mera-missing-badge"><i class="fas fa-tags" aria-hidden="true"></i> {{ $report->pendingCount() }} por clasificar</span>
                            @endif
                            @if ($report->isShared())
                            <span class="mera-status mera-status-on mt-1"><i class="fas fa-link" aria-hidden="true"></i> Compartido</span>
                            @endif
                        </td>
                        <td data-label="Completaron" class="mera-nowrap">
                            {{ isset($s['pct_completado']) ? round($s['pct_completado']).' %' : '—' }}
                            <small class="d-block text-muted">{{ number_format($s['completados'] ?? 0) }} de {{ number_format($s['asignados'] ?? 0) }}</small>
                        </td>
                        <td data-label="Promedio">{{ isset($s['calif_promedio']) ? number_format($s['calif_promedio'], 1) : '—' }}</td>
                        <td data-label="Satisfacción" class="mera-nowrap">
                            {{ isset($s['satisfaccion']) ? round($s['satisfaccion']).' %' : '—' }}
                            <small class="d-block text-muted">{{ number_format($s['respuestas'] ?? 0) }} respuestas</small>
                        </td>
                        <td data-label="Acciones" class="text-center text-nowrap mera-col-actions">
                            <a href="{{ route('course-groups.reports.show', [$group, $report]) }}" class="mera-action-btn mera-add-btn" title="Ver reporte" aria-label="Ver reporte del {{ $report->cut_date->format('d/m/Y') }}">
                                <i class="fa fa-eye" aria-hidden="true"></i>
                            </a>
                            <a href="{{ route('course-groups.reports.edit', [$group, $report]) }}" class="mera-action-btn mera-edit-btn" title="Actualizar corte" aria-label="Actualizar el corte del {{ $report->cut_date->format('d/m/Y') }}">
                                <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</section>
