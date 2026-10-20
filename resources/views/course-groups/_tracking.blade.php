{{-- El curso de esta versión en el seguimiento anual (último corte), por su código del catálogo
     (decisión del usuario 2026-10-20: conectar ambos reportes). Variables: $group, $version, $tracking --}}
@php $t = $tracking['totals'] ?? null; @endphp
<section class="card mera-table-card mb-3" aria-labelledby="seguimiento-version">
    <div class="card-body">
        <div class="mera-section-toolbar">
            <div>
                <h2 id="seguimiento-version" class="h6 mb-1">En el seguimiento anual</h2>
                <small class="text-muted">
                    @if ($tracking['state'] === 'ok')
                    {{ $tracking['course']->short_title }} · corte al {{ $tracking['report']->cut_date->format('d/m/Y') }} (todas las localidades)
                    @else
                    Las cifras de este curso en el reporte general, sin subir más archivos.
                    @endif
                </small>
            </div>
            @if ($tracking['state'] === 'ok')
            <a href="{{ $tracking['url'] }}" class="btn btn-sm mera-btn-ghost"><i class="fas fa-chart-line" aria-hidden="true"></i> Ver en el seguimiento</a>
            @endif
        </div>

        @switch($tracking['state'])
            @case('ok')
            <div class="mera-track-cards mb-0">
                <div class="mera-track-card"><span>Asignados</span><b>{{ number_format($t['assigned']) }}</b><small>personas con el curso</small></div>
                <div class="mera-track-card is-main"><span>Realizado</span><b>{{ $t['pct_done'] }} %</b><small>{{ number_format($t['done']) }} vieron el curso y presentaron</small></div>
                <div class="mera-track-card"><span>Aprobado</span><b>{{ $t['pct_passed'] }} %</b><small>{{ number_format($t['passed']) }} con {{ $tracking['course']->has_evaluation ? $tracking['course']->passing_grade.' o más' : 'el curso completo' }}</small></div>
                <div class="mera-track-card"><span>Sin iniciar</span><b>{{ $t['pct_not_started'] }} %</b><small>{{ number_format($t['not_started']) }} · {{ number_format($t['in_progress']) }} en curso</small></div>
                @if ($tracking['survey'])
                <div class="mera-track-card"><span>Encuesta</span><b>{{ $tracking['survey']->indicator('recommend') ?? '—' }} %</b><small>recomienda · NPS {{ $tracking['survey']->indicator('nps') === null ? '—' : sprintf('%+d', $tracking['survey']->indicator('nps')) }} · {{ number_format($tracking['survey']->responses) }} respuestas</small></div>
                @endif
            </div>
            @break
            @case('no_code')
            <p class="small text-muted mb-0">
                Esta versión no tiene <strong>código del catálogo</strong>. Agrégalo en
                <a href="{{ route('course-groups.versions.edit', [$group, $version]) }}">Editar versión</a> (p. ej. NRM-HIG-002-EL-26) para ver aquí sus cifras del seguimiento.
            </p>
            @break
            @case('not_in_catalog')
            <p class="small text-muted mb-0">El código <code class="mera-code-badge">{{ $version->catalog_code }}</code> no está en el Catálogo de cursos. Revisa el código o pide que se cargue el catálogo actualizado.</p>
            @break
            @case('not_tracked')
            <p class="small text-muted mb-0">{{ $tracking['course']->short_title }} no cuenta para el seguimiento anual (es de un año anterior o está marcado «No cuenta» en el Catálogo de cursos).</p>
            @break
            @default
            <p class="small text-muted mb-0">{{ $tracking['course']->short_title }} todavía no aparece en ningún corte del seguimiento anual.</p>
        @endswitch
    </div>
</section>
