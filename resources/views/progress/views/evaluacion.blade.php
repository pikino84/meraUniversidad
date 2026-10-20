{{-- Pestaña Evaluación: calificaciones por rango, promedio por curso y dónde reprueban más. --}}
<div class="mera-track-grid">
    <section class="card mera-table-card" aria-labelledby="e-rangos">
        <div class="card-body">
            <h2 id="e-rangos" class="h6 mera-track-title mb-1">Calificaciones</h2>
            @if ($bands['n'] === 0)
            <p class="text-muted small mb-0">Nadie ha presentado la evaluación con estos filtros.</p>
            @else
            <p class="small text-muted">{{ number_format($bands['n']) }} evaluaciones presentadas. Aprobar pide 80 o más (90 en Corresponsal Cambiario).</p>
            <ol class="mera-track-months list-unstyled mb-0">
                @foreach (['b90' => '90 a 100', 'b80' => '80 a 89', 'b70' => '70 a 79', 'b60' => '60 a 69', 'b0' => 'Menos de 60'] as $key => $label)
                <li>
                    <span class="mera-track-month">{{ $label }}</span>
                    <span class="mera-track-bar"><i class="{{ in_array($key, ['b90', 'b80'], true) ? 'is-passed' : 'is-failed' }}" style="width: {{ $bands[$key] / $bands['n'] * 100 }}%"></i></span>
                    <span class="mera-track-bar-value"><b>{{ round($bands[$key] / $bands['n'] * 100) }} %</b> <small>{{ number_format($bands[$key]) }}</small></span>
                </li>
                @endforeach
            </ol>
            @endif
        </div>
    </section>

    <section class="card mera-table-card" aria-labelledby="e-reprueban">
        <div class="card-body">
            <h2 id="e-reprueban" class="h6 mera-track-title mb-1">Dónde reprueban más</h2>
            <p class="small text-muted">% de reprobados entre quienes presentaron (con al menos {{ config('mera.progress.dashboard.failing_min_graded') }} evaluaciones{{ $onlyGenerals ? ', cursos generales' : '' }}).</p>
            @include('progress._failing')
        </div>
    </section>
</div>

<section class="card mera-table-card mt-3" aria-labelledby="e-cursos">
    <div class="card-body">
        <h2 id="e-cursos" class="h6 mera-track-title mb-1">Promedio por curso</h2>
        <p class="small text-muted">De menor a mayor. Entre paréntesis, cuántos presentaron.</p>
        @if ($courses->isEmpty())
        <p class="text-muted small mb-0">Sin evaluaciones con estos filtros.</p>
        @else
        <ol class="mera-track-months mera-track-grades list-unstyled mb-0">
            @foreach ($courses as $r)
            <li>
                <span class="mera-track-month"><a href="{{ $with(['curso' => $r['k']]) }}">{{ $r['label'] }}</a></span>
                <span class="mera-track-bar"><i class="{{ $r['avg_grade'] >= 80 ? 'is-passed' : 'is-failed' }}" style="width: {{ $r['avg_grade'] }}%"></i></span>
                <span class="mera-track-bar-value"><b>{{ $r['avg_grade'] }}</b> <small>({{ number_format($r['done']) }})</small></span>
            </li>
            @endforeach
        </ol>
        @endif
    </div>
</section>
