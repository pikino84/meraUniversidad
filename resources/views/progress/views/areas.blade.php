{{-- Pestaña Áreas y puestos. --}}
<div class="mera-track-grid">
    <section class="card mera-table-card" aria-labelledby="a-areas">
        <div class="card-body">
            <h2 id="a-areas" class="h6 mera-track-title mb-1">Avance por área</h2>
            <p class="small text-muted">Por % realizado{{ $onlyGenerals ? ' en cursos generales' : '' }}. Toca una para filtrar todo el tablero.</p>
            @include('progress._legend')
            @include('progress._bars', ['rows' => $areas, 'link' => $filters['area'] ? null : fn ($a) => $with(['area' => $a])])
        </div>
    </section>

    <section class="card mera-table-card" aria-labelledby="a-puestos">
        <div class="card-body">
            <h2 id="a-puestos" class="h6 mera-track-title mb-1">Puestos con menor avance</h2>
            <p class="small text-muted">Los 15 puestos con menor % realizado entre los que tienen al menos {{ config('mera.progress.dashboard.position_min_people') }} personas{{ $onlyGenerals ? ' (cursos generales)' : '' }}.</p>
            @include('progress._bars', ['rows' => $positions])
        </div>
    </section>
</div>
