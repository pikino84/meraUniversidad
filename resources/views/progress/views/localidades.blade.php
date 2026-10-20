{{-- Pestaña Localidades: ranking por % realizado y dónde reprueban más. --}}
<div class="mera-track-grid">
    <section class="card mera-table-card" aria-labelledby="l-ranking">
        <div class="card-body">
            <h2 id="l-ranking" class="h6 mera-track-title mb-1">Ranking de localidades</h2>
            <p class="small text-muted">Con al menos {{ config('mera.progress.dashboard.ranking_min_people') }} personas, por % realizado{{ $onlyGenerals ? ' en cursos generales' : '' }}. Toca una para filtrar todo el tablero.</p>
            @include('progress._legend')
            @include('progress._bars', ['rows' => $localities, 'numbered' => true, 'deltas' => $deltas['localities'] ?? [], 'link' => $filters['localidad'] ? null : fn ($l) => $with(['localidad' => $l])])
        </div>
    </section>

    <section class="card mera-table-card" aria-labelledby="l-reprueban">
        <div class="card-body">
            <h2 id="l-reprueban" class="h6 mera-track-title mb-1">Dónde reprueban más</h2>
            <p class="small text-muted">% de reprobados entre quienes presentaron la evaluación (con al menos {{ config('mera.progress.dashboard.failing_min_graded') }} evaluaciones{{ $onlyGenerals ? ', cursos generales' : '' }}). Toca una para ver a las personas.</p>
            @include('progress._failing')
        </div>
    </section>
</div>
