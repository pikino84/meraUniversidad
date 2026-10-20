{{-- "Descargar HTML" del seguimiento anual (INSTRUCTIVO_REPORTE_PERSONAS.md §7.2.8): un solo archivo que abre
     sin servidor, con los filtros con los que se descargó. Solo agregados: sin nombres, sin consulta de
     colaboradores ni respuestas abiertas; las respuestas cerradas con menos de 3 menciones se agrupan.
     Estilos y script incrustados; solo Montserrat se pide a Google Fonts (sin internet usa Arial). --}}
@php
    $tz = config('mera.display_timezone');
    $logo = public_path('images/logo-mera-university-blanco.png');
    $t = $totals;
    $c = $comparable;
    $onlyGenerals = $d->generalsOnly();
    $a = max(1, $t['assigned']);
    $segments = [['is-passed', 'Aprobado', $t['passed']], ['is-failed', 'Reprobado', $t['failed']], ['is-progress', 'En curso', $t['in_progress']], ['is-empty', 'Sin iniciar', $t['not_started']]];
    $monthNames = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr', '05' => 'may', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic'];
    $maxMonth = max(1, $months->max('started') ?? 1);
    $applied = collect([
        'Curso' => $filters['curso'] ? ($labels[$filters['curso']] ?? $filters['curso']) : null,
        'Ruta' => $filters['ruta'] ? ($options['routes'][$filters['ruta']] ?? $filters['ruta']) : null,
        'Localidad' => $filters['localidad'],
        'Área' => $filters['area'],
        'Centro de trabajo' => $filters['centro'],
    ])->filter();
    $sections = array_filter([
        'resumen' => 'Resumen',
        'cursos' => 'Cursos',
        'localidades' => $localities->isNotEmpty() ? 'Localidades' : null,
        'areas' => 'Áreas y puestos',
        'evaluacion' => 'Evaluación',
        'opiniones' => $surveys->isNotEmpty() ? 'Opiniones' : null,
    ]);
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').' %';
    $k = $deltas['kpi'] ?? [];
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $report->title }}{{ $filters['localidad'] ? ' · '.$filters['localidad'] : '' }} · Corte al {{ $report->cut_date->format('d/m/Y') }}</title>
@include('partials.brand-fonts')
<style>
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; background: #F3F8F4; color: #1d2a22; font: 14px/1.45 'Montserrat', Arial, sans-serif; }
a { color: #024D25; }
.wrap { max-width: 1180px; margin: 0 auto; padding: 1.5rem 1.25rem 3rem; }
.head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; padding: 1.3rem 1.5rem; border-radius: 18px; color: #fff; background: linear-gradient(90deg, #024D25, #55882B); }
.head h1 { margin: 0 0 .25rem; font-size: 1.55rem; }
.head p { margin: 0; opacity: .92; }
.head img { height: 56px; width: auto; }
.applied { margin: 0 0 1rem; color: #5F6368; font-size: 13px; }
.applied b { color: #024D25; }
.kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 1rem; }
.kpi { display: flex; flex-direction: column; gap: 2px; padding: 14px 16px; border-radius: 16px; background: #fff; border: 1px solid #dfe9e2; }
.kpi.is-main { background: #024D25; border-color: #024D25; color: #fff; }
.kpi span { font-size: 13px; font-weight: 700; color: inherit; opacity: .85; }
.kpi b { font-size: 28px; line-height: 1.15; color: #024D25; }
.kpi.is-main b { color: #fff; }
.kpi small { font-size: 12px; opacity: .85; }
.tabs { display: flex; flex-wrap: wrap; gap: 4px; margin: 0 0 14px; padding: 0; border-bottom: 1px solid #dfe9e2; list-style: none; }
.tabs button { margin-bottom: -1px; padding: 9px 14px; border: 0; border-bottom: 3px solid transparent; background: none; color: #5F6368; font: 600 14px 'Montserrat', Arial, sans-serif; cursor: pointer; }
.tabs button[aria-selected="true"] { border-bottom-color: #024D25; color: #024D25; }
.card { margin-bottom: 14px; padding: 16px 18px; border-radius: 16px; background: #fff; border: 1px solid #dfe9e2; }
.card h2 { margin: 0 0 .4rem; color: #024D25; font-size: 15px; }
.grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.grid .card { margin-bottom: 0; }
.muted { color: #5F6368; font-size: 12px; }
.mera-track-bars, .mera-track-months { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
.mera-track-bar-row, .mera-track-months li { display: grid; grid-template-columns: minmax(0, 38%) minmax(0, 1fr) 96px; gap: 10px; align-items: center; font-size: 13px; }
.mera-track-months li { grid-template-columns: 110px minmax(0, 1fr) 110px; }
.mera-track-bar-label small { display: block; color: #5F6368; font-size: 11px; }
.mera-track-rank { display: inline-block; min-width: 20px; color: #5F6368; }
.mera-track-bar { display: flex; height: 12px; overflow: hidden; border-radius: 999px; background: #E6ECE8; }
.mera-track-bar.is-big { height: 22px; margin: 6px 0 12px; }
.mera-track-bar i, .legend i, .breakdown i { display: block; }
.is-passed { background: #024D25; } .is-failed { background: #E8A317; } .is-progress { background: #81CFF4; } .is-empty { background: #E6ECE8; }
.mera-track-bar-value { text-align: right; white-space: nowrap; }
.mera-track-bar-value small { color: #5F6368; font-size: 11px; }
.legend, .breakdown { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; margin: 0 0 8px; padding: 0; list-style: none; color: #5F6368; font-size: 12px; }
.legend i, .breakdown i { display: inline-block; width: 12px; height: 12px; margin-right: 4px; border-radius: 3px; vertical-align: -1px; }
.breakdown { font-size: 14px; color: #1d2a22; }
.mera-scope-badge { display: inline-block; margin-left: 4px; padding: 0 6px; border-radius: 999px; background: #E3F0D0; color: #024D25; font-size: 10px; font-weight: 700; }
.mera-scope-badge.is-specific { background: #EDE7F6; color: #4527A0; }
.mera-scope-badge.is-bl { background: #E5F5FD; color: #0B5A7A; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th { padding: 8px; background: #024D25; color: #fff; text-align: left; font-size: 12px; }
td { padding: 8px; border-bottom: 1px solid #E6ECE8; vertical-align: top; }
.foot { margin-top: 1.5rem; font-size: .8rem; color: #5d6b63; }
.mera-delta { display: inline-block; margin-left: 4px; padding: 1px 7px; border-radius: 999px; font-size: 12px; font-weight: 700; vertical-align: middle; white-space: nowrap; }
.mera-delta.is-up { background: #E3F0D0; color: #1B5E20; } .mera-delta.is-down { background: #FDECEC; color: #B71C1C; } .mera-delta.is-same { background: #EEF2EF; color: #5F6368; }
.kpi.is-main .mera-delta.is-up { background: rgba(255,255,255,.2); color: #fff; }
.mera-track-bar-value .mera-delta { padding: 0 5px; font-size: 10px; }
.visually-hidden { position: absolute !important; width: 1px !important; height: 1px !important; overflow: hidden !important; clip: rect(0, 0, 0, 0) !important; white-space: nowrap !important; }
@media (max-width: 800px) { .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .grid { grid-template-columns: 1fr; } .mera-track-bar-row { grid-template-columns: 1fr 84px; } .mera-track-bar-row .mera-track-bar { grid-column: 1 / -1; grid-row: 2; } }
@media print { .tabs { display: none; } section[data-tab] { display: block !important; } }
</style>
</head>
<body>
@include('partials.test-environment')
<main class="wrap">
    <header class="head">
        <div>
            <h1>{{ $report->title }}{{ $filters['localidad'] ? ' · '.$filters['localidad'] : '' }}</h1>
            <p>Seguimiento de capacitación · corte al {{ $report->cut_date->translatedFormat('j \d\e F \d\e Y') }} · {{ number_format($t['people']) }} personas · {{ $filters['anio'] }}</p>
        </div>
        @if (is_file($logo))
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logo)) }}" alt="MERA University">
        @endif
    </header>

    @if ($compare)
    <p class="applied">Cambios contra el corte del <b>{{ $compare->cut_date->format('d/m/Y') }}</b> ({{ $compare->title }}), con los mismos filtros.</p>
    @endif
    @if ($applied->isNotEmpty())
    <p class="applied">Filtros: @foreach ($applied as $name => $value)<b>{{ $name }}:</b> {{ $value }}@if (! $loop->last) · @endif @endforeach</p>
    @endif

    <section class="kpis" aria-label="Indicadores">
        <div class="kpi is-main"><span>Realizado</span><b>{{ $t['pct_done'] }} % @include('progress._delta', ['value' => $k['pct_done'] ?? null])</b><small>{{ number_format($t['done']) }} de {{ number_format($t['assigned']) }} cursos asignados</small></div>
        <div class="kpi"><span>Aprobado</span><b>{{ $t['pct_passed'] }} % @include('progress._delta', ['value' => $k['pct_passed'] ?? null])</b><small>{{ number_format($t['passed']) }} · {{ number_format($t['failed']) }} reprobados</small></div>
        <div class="kpi"><span>Sin iniciar</span><b>{{ $t['pct_not_started'] }} % @include('progress._delta', ['value' => $k['pct_not_started'] ?? null, 'inverse' => true])</b><small>{{ number_format($t['not_started']) }} · {{ number_format($t['in_progress']) }} en curso</small></div>
        <div class="kpi"><span>Calificación promedio</span><b>{{ $t['avg_grade'] ?? '—' }} @include('progress._delta', ['value' => $k['avg_grade'] ?? null, 'unit' => ''])</b><small>{{ $c['courses_per_person'] }} cursos y {{ $c['hours_real_per_person'] }} de {{ $c['hours_per_person'] }} h por persona{{ $onlyGenerals ? ' (generales)' : '' }}</small></div>
    </section>

    <ul class="tabs" role="tablist" data-tabs hidden>
        @foreach ($sections as $key => $label)
        <li><button type="button" role="tab" data-tab-button="{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button></li>
        @endforeach
    </ul>

    <section data-tab="resumen">
        <div class="card">
            <h2>Cómo van los {{ number_format($t['assigned']) }} cursos asignados</h2>
            <div class="mera-track-bar is-big">@foreach ($segments as [$class, , $n])<i class="{{ $class }}" style="width: {{ $n / $a * 100 }}%"></i>@endforeach</div>
            <ul class="breakdown">@foreach ($segments as [$class, $label, $n])<li><i class="{{ $class }}"></i> <b>{{ round($n / $a * 100, 1) }} %</b> {{ $label }} <small class="muted">{{ number_format($n) }}</small></li>@endforeach</ul>
        </div>
        <div class="card">
            <h2>Avance mes a mes</h2>
            <p class="muted">Por mes en que empezaron: iniciaron, ya realizados y acumulado sobre lo asignado.</p>
            <ol class="mera-track-months">
                @foreach ($months as $m)
                @php([$y, $mm] = explode('-', $m['month']))
                <li>
                    <span>{{ $monthNames[$mm] ?? $mm }} {{ substr($y, 2) }}</span>
                    <span class="mera-track-bar"><i class="is-passed" style="width: {{ $m['done'] / $maxMonth * 100 }}%"></i><i class="is-progress" style="width: {{ ($m['started'] - $m['done']) / $maxMonth * 100 }}%"></i></span>
                    <span class="mera-track-bar-value"><b>{{ number_format($m['started']) }}</b> <small>acum. {{ round($m['pct_cumulative']) }} %</small></span>
                </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section data-tab="cursos">
        <div class="card">
            <h2>Cómo va cada curso (de menor a mayor avance)</h2>
            <ul class="legend"><li><i class="is-passed"></i>Aprobado</li><li><i class="is-failed"></i>Reprobado</li><li><i class="is-progress"></i>En curso</li><li><i class="is-empty"></i>Sin iniciar</li><li>· el número es el % realizado</li></ul>
            @include('progress._bars', ['rows' => $courses, 'deltas' => $deltas['courses'] ?? []])
        </div>
        @if ($specific->isNotEmpty())
        <div class="card">
            <h2>Cursos específicos y presenciales</h2>
            <p class="muted">Para ciertas áreas o puestos, y los presenciales (BL). En un BL, el avance mide las actividades en línea.</p>
            <table>
                <thead><tr><th>Curso</th><th>A quién va</th><th>Realizado</th><th>Aprobado</th><th>Sin iniciar</th></tr></thead>
                <tbody>
                    @foreach ($specific as $s)
                    <tr>
                        <td>{{ $s['label'] }} @if ($s['modality'] === 'BL')<span class="mera-scope-badge is-bl">BL</span>@endif<br><small class="muted">{{ number_format($s['assigned']) }} asignados</small></td>
                        <td><small>{{ collect($s['areas'])->map(fn ($n, $x) => $x.' ('.number_format($n).')')->join(', ') }}</small></td>
                        <td>{{ round($s['pct_done']) }} %</td><td>{{ round($s['pct_passed']) }} %</td><td>{{ round($s['pct_not_started']) }} %</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </section>

    @if ($localities->isNotEmpty())
    <section data-tab="localidades">
        <div class="grid">
            <div class="card">
                <h2>Ranking de localidades</h2>
                <p class="muted">Con al menos {{ config('mera.progress.dashboard.ranking_min_people') }} personas, por % realizado{{ $onlyGenerals ? ' en cursos generales' : '' }}.</p>
                @include('progress._bars', ['rows' => $localities, 'numbered' => true, 'deltas' => $deltas['localities'] ?? []])
            </div>
            <div class="card">
                <h2>Dónde reprueban más</h2>
                <p class="muted">% de reprobados entre quienes presentaron.</p>
                @include('progress._failing', ['share' => true])
            </div>
        </div>
    </section>
    @endif

    <section data-tab="areas">
        <div class="grid">
            <div class="card">
                <h2>Avance por área</h2>
                @include('progress._bars', ['rows' => $areas])
            </div>
            <div class="card">
                <h2>Puestos con menor avance</h2>
                <p class="muted">Con al menos {{ config('mera.progress.dashboard.position_min_people') }} personas.</p>
                @include('progress._bars', ['rows' => $positions])
            </div>
        </div>
    </section>

    <section data-tab="evaluacion">
        <div class="card">
            <h2>Calificaciones</h2>
            @if ($bands['n'] === 0)
            <p class="muted">Nadie ha presentado la evaluación con estos filtros.</p>
            @else
            <p class="muted">{{ number_format($bands['n']) }} evaluaciones presentadas. Aprobar pide 80 o más (90 en Corresponsal Cambiario).</p>
            <ol class="mera-track-months">
                @foreach (['b90' => '90 a 100', 'b80' => '80 a 89', 'b70' => '70 a 79', 'b60' => '60 a 69', 'b0' => 'Menos de 60'] as $key => $label)
                <li><span>{{ $label }}</span><span class="mera-track-bar"><i class="{{ in_array($key, ['b90', 'b80'], true) ? 'is-passed' : 'is-failed' }}" style="width: {{ $bands[$key] / $bands['n'] * 100 }}%"></i></span><span class="mera-track-bar-value"><b>{{ round($bands[$key] / $bands['n'] * 100) }} %</b> <small>{{ number_format($bands[$key]) }}</small></span></li>
                @endforeach
            </ol>
            @endif
        </div>
    </section>

    @if ($surveys->isNotEmpty())
    <section data-tab="opiniones">
        <div class="card">
            <h2>Lo que opinan</h2>
            <p class="muted">Encuestas de satisfacción por curso (todas las localidades). NPS = % que puso 9–10 menos % que puso 0–6.</p>
            <table>
                <thead><tr><th>Curso</th><th>Respuestas</th><th>Recomienda</th><th>NPS</th><th>Problemas técnicos</th><th>Satisfacción</th></tr></thead>
                <tbody>
                    @foreach ($surveys as $s)
                    <tr>
                        <td>{{ $labels[$s->course_code] ?? $s->course_code }}</td>
                        <td>{{ number_format($s->responses) }}</td>
                        <td>{{ $pct($s->indicator('recommend')) }}</td>
                        <td>{{ $s->indicator('nps') === null ? '—' : sprintf('%+d', $s->indicator('nps')) }}</td>
                        <td>{{ $pct($s->indicator('tech_problems')) }}</td>
                        <td>{{ $pct($s->indicator('satisfaction')) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif

    <p class="foot">
        Generado el {{ now($tz)->format('d/m/Y H:i') }} desde Mera Universidad. Solo incluye totales por curso, localidad, área y puesto:
        ningún nombre ni número de empleado.
    </p>
</main>
<script>
// Pestañas sin servidor. Sin JavaScript (o al imprimir) se ven todas las secciones.
(function () {
    var list = document.querySelector('[data-tabs]');
    var buttons = list.querySelectorAll('[data-tab-button]');
    var sections = document.querySelectorAll('[data-tab]');
    function show(key) {
        sections.forEach(function (s) { s.hidden = s.getAttribute('data-tab') !== key; });
        buttons.forEach(function (b) { b.setAttribute('aria-selected', b.getAttribute('data-tab-button') === key ? 'true' : 'false'); });
    }
    list.hidden = false;
    buttons.forEach(function (b) { b.addEventListener('click', function () { show(b.getAttribute('data-tab-button')); }); });
    show(buttons[0].getAttribute('data-tab-button'));
})();
</script>
</body>
</html>
