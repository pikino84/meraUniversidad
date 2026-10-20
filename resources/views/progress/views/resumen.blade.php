{{-- Pestaña Resumen: cómo va el año en una barra, lo que requiere atención y el avance mes a mes. --}}
@php
    $a = max(1, $t['assigned']);
    $segments = [
        ['is-passed', 'Aprobado', $t['passed']],
        ['is-failed', 'Reprobado', $t['failed']],
        ['is-progress', 'En curso', $t['in_progress']],
        ['is-empty', 'Sin iniciar', $t['not_started']],
    ];
    $monthNames = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr', '05' => 'may', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic'];
    $maxMonth = max(1, $months->max('started') ?? 1);
@endphp

<section class="card mera-table-card" aria-labelledby="r-avance">
    <div class="card-body">
        <h2 id="r-avance" class="h6 mera-track-title">Cómo van los {{ number_format($t['assigned']) }} cursos asignados</h2>
        <div class="mera-track-bar is-big" role="img"
            aria-label="{{ collect($segments)->map(fn ($s) => $s[1].' '.round($s[2] / $a * 100, 1).' %')->join(', ') }}">
            @foreach ($segments as [$class, $label, $n])
            <i class="{{ $class }}" style="width: {{ $n / $a * 100 }}%"></i>
            @endforeach
        </div>
        <ul class="mera-track-breakdown list-unstyled mb-0">
            @foreach ($segments as [$class, $label, $n])
            <li><i class="{{ $class }}" aria-hidden="true"></i> <b>{{ round($n / $a * 100, 1) }} %</b> {{ $label }} <small>{{ number_format($n) }}</small></li>
            @endforeach
        </ul>
    </div>
</section>

@php($perLocality = $share && $share->locality !== null) {{-- link de una localidad: sin comparar localidades --}}
<div class="{{ $perLocality ? '' : 'mera-track-grid' }} mt-3">
    <section class="card mera-table-card" aria-labelledby="r-cursos">
        <div class="card-body">
            <div class="mera-section-toolbar mb-2">
                <h2 id="r-cursos" class="h6 mera-track-title mb-0">Cursos con menor avance</h2>
                <a href="{{ $with(['vista' => 'cursos']) }}" class="small">Ver los {{ $courses->count() }} cursos →</a>
            </div>
            @include('progress._bars', ['rows' => $attentionCourses, 'link' => $filters['curso'] ? null : fn ($code) => $with(['curso' => $code, 'vista' => 'cursos'])])
        </div>
    </section>

    @unless ($perLocality)
    <section class="card mera-table-card" aria-labelledby="r-localidades">
        <div class="card-body">
            <div class="mera-section-toolbar mb-2">
                <h2 id="r-localidades" class="h6 mera-track-title mb-0">Localidades con menor avance</h2>
                <a href="{{ $with(['vista' => 'localidades']) }}" class="small">Ver el ranking →</a>
            </div>
            @include('progress._bars', ['rows' => $attentionLocalities, 'link' => $filters['localidad'] ? null : fn ($l) => $with(['localidad' => $l])])
            @if ($onlyGenerals)<p class="small text-muted mt-2 mb-0">En cursos generales.</p>@endif
        </div>
    </section>
    @endunless
</div>

<section class="card mera-table-card mt-3" aria-labelledby="r-meses">
    <div class="card-body">
        <h2 id="r-meses" class="h6 mera-track-title">Avance mes a mes</h2>
        <p class="small text-muted">Por mes en que empezaron: cuántos iniciaron, cuántos ya lo realizaron y el acumulado sobre lo asignado.</p>
        @if ($months->isEmpty())
        <p class="text-muted small mb-0">Sin datos con estos filtros.</p>
        @else
        <ol class="mera-track-months list-unstyled mb-0">
            @foreach ($months as $m)
            @php([$y, $mm] = explode('-', $m['month']))
            <li>
                <span class="mera-track-month">{{ $monthNames[$mm] ?? $mm }} {{ substr($y, 2) }}</span>
                <span class="mera-track-bar" role="img" aria-label="{{ $m['started'] }} iniciaron, {{ $m['done'] }} realizados">
                    <i class="is-passed" style="width: {{ $m['done'] / $maxMonth * 100 }}%"></i>
                    <i class="is-progress" style="width: {{ ($m['started'] - $m['done']) / $maxMonth * 100 }}%"></i>
                </span>
                <span class="mera-track-bar-value"><b>{{ number_format($m['started']) }}</b> <small>acum. {{ round($m['pct_cumulative']) }} %</small></span>
            </li>
            @endforeach
        </ol>
        @endif
    </div>
</section>
