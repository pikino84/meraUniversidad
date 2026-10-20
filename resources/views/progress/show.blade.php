@extends('layouts.app')

@section('title', $report->title.' · Seguimiento anual')

@section('content')
@php
    $base = array_filter($filters);
    $with = fn (array $changes) => route('progress.show', array_filter(array_merge(['report' => $report] + $base, $changes)));
    $t = $totals;
    $monthNames = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr', '05' => 'may', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic'];
    $maxMonth = max(1, $months->max('started') ?? 1);
    $c = $comparable;
    $onlyGenerals = $d->generalsOnly();
    $generalsNote = $onlyGenerals ? ' (generales)' : '';
@endphp

<x-folder-header :crumbs="[['Seguimiento anual', route('progress.index')], [$report->cut_date->format('d/m/Y'), null]]"
    :title="$report->title" :subtitle="'Corte al '.$report->cut_date->translatedFormat('j \d\e F \d\e Y').' · todos los cursos que cuentan del año'">
    <a href="{{ route('progress.people', ['report' => $report] + $base) }}" class="btn btn-sm mera-btn-ghost"><i class="fas fa-users" aria-hidden="true"></i> Consulta de colaboradores</a>
</x-folder-header>

@if ($courseVersions->isNotEmpty())
<p class="mera-track-scope-note small">
    <i class="fas fa-folder-open" aria-hidden="true"></i> Este curso en las carpetas:
    @foreach ($courseVersions as $v)
    <a href="{{ route('course-groups.versions.show', [$v->group, $v, 'seccion' => 'reportes']) }}">{{ $v->group->name }} · {{ $v->label() }}</a>@if (! $loop->last), @endif
    @endforeach
    (ahí están sus cortes por curso).
</p>
@endif

@if ($report->warnings)
<details class="alert alert-warning mera-track-warnings">
    <summary><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> {{ count($report->warnings) }} {{ count($report->warnings) === 1 ? 'aviso' : 'avisos' }} de este corte</summary>
    <ul class="mb-0 mt-2">
        @foreach ($report->warnings as $warning)<li>{{ $warning }}</li>@endforeach
    </ul>
</details>
@endif

@if (! $filters['anio'])
<div class="card mera-table-card"><div class="card-body">
    <div class="mera-empty-state">
        <i class="fas fa-calendar" aria-hidden="true"></i>
        <strong>Este corte no tiene cursos de {{ config('mera.progress.tracking_from_year') }} en adelante</strong>
        <span>Solo cuentan los cursos de ese año en adelante (Catálogo de cursos → «Cuenta para el seguimiento»).</span>
    </div>
</div></div>
@else

@include('progress._filters', ['action' => route('progress.show', $report), 'filters' => $filters, 'options' => $options])

<section class="mera-track-cards" aria-label="Resumen">
    <div class="mera-track-card">
        <span>Personas</span><b>{{ number_format($t['people']) }}</b>
        <small>{{ number_format($t['assigned']) }} asignaciones · {{ $c['courses_per_person'] }} cursos por persona{{ $generalsNote }}</small>
    </div>
    <div class="mera-track-card is-main">
        <span>Realizado</span><b>{{ $t['pct_done'] }} %</b>
        <small>{{ number_format($t['done']) }} vieron el curso y presentaron la evaluación</small>
    </div>
    <div class="mera-track-card">
        <span>Aprobado</span><b>{{ $t['pct_passed'] }} %</b>
        <small>{{ number_format($t['passed']) }} aprobados · {{ number_format($t['failed']) }} reprobados</small>
    </div>
    <div class="mera-track-card">
        <span>Sin iniciar</span><b>{{ $t['pct_not_started'] }} %</b>
        <small>{{ number_format($t['not_started']) }} · {{ number_format($t['in_progress']) }} en curso</small>
    </div>
    <div class="mera-track-card">
        <span>Calificación promedio</span><b>{{ $t['avg_grade'] ?? '—' }}</b>
        <small>de quienes presentaron la evaluación</small>
    </div>
    <div class="mera-track-card">
        <span>Horas por persona{{ $generalsNote }}</span><b>{{ $c['hours_real_per_person'] }} h</b>
        <small>de {{ $c['hours_per_person'] }} h asignadas ({{ $c['pct_hours'] }} %)</small>
    </div>
</section>

<p class="mera-track-legend small text-muted" aria-hidden="true">
    <i class="is-passed"></i> Aprobado <i class="is-failed"></i> Reprobado <i class="is-progress"></i> En curso <i class="is-empty"></i> Sin iniciar
    · el % es lo realizado de sus cursos asignados
</p>
@if ($onlyGenerals)
<p class="mera-track-scope-note small" role="note">
    <i class="fas fa-info-circle" aria-hidden="true"></i>
    El ranking, el avance por área, «dónde reprueban más» y los cursos y horas por persona comparan <strong>solo cursos generales</strong>
    (para todos), para que no pesen los cursos de ciertas áreas o puestos. Actívalo arriba para incluir los específicos.
</p>
@endif

<div class="mera-track-grid">
    <section class="card mera-table-card" aria-labelledby="t-cursos">
        <div class="card-body">
            <h2 id="t-cursos" class="h6 mera-track-title">Cómo va cada curso</h2>
            @include('progress._bars', ['rows' => $courses, 'link' => $filters['curso'] ? null : fn ($code) => $with(['curso' => $code])])
        </div>
    </section>

    <section class="card mera-table-card" aria-labelledby="t-localidades">
        <div class="card-body">
            <h2 id="t-localidades" class="h6 mera-track-title">Ranking de localidades</h2>
            <p class="small text-muted">Localidades con al menos {{ config('mera.progress.dashboard.ranking_min_people') }} personas, por % realizado{{ $onlyGenerals ? ' en cursos generales' : '' }}.</p>
            @include('progress._bars', ['rows' => $localities, 'numbered' => true, 'link' => $filters['localidad'] ? null : fn ($l) => $with(['localidad' => $l])])
        </div>
    </section>

    <section class="card mera-table-card" aria-labelledby="t-areas">
        <div class="card-body">
            <h2 id="t-areas" class="h6 mera-track-title">Avance por área</h2>
            @if ($onlyGenerals)<p class="small text-muted">En cursos generales.</p>@endif
            @include('progress._bars', ['rows' => $areas, 'link' => $filters['area'] ? null : fn ($a) => $with(['area' => $a])])
        </div>
    </section>

    <section class="card mera-table-card" aria-labelledby="t-meses">
        <div class="card-body">
            <h2 id="t-meses" class="h6 mera-track-title">Avance mes a mes</h2>
            <p class="small text-muted">Por mes en que empezaron: cuántos iniciaron, cuántos de ellos ya lo realizaron y el acumulado sobre lo asignado.</p>
            @if ($months->isEmpty())
            <p class="text-muted small mb-0">Sin datos con estos filtros.</p>
            @else
            <ol class="mera-track-months list-unstyled mb-0">
                @foreach ($months as $m)
                @php([$y, $mm] = explode('-', $m['month']))
                <li>
                    <span class="mera-track-month">{{ $monthNames[$mm] ?? $mm }} {{ substr($y, 2) }}</span>
                    <span class="mera-track-bar" role="img" aria-label="{{ $m['started'] }} iniciaron, {{ $m['done'] }} realizados">
                        <i class="is-progress" style="width: {{ ($m['started'] - $m['done']) / $maxMonth * 100 }}%; order: 2"></i>
                        <i class="is-passed" style="width: {{ $m['done'] / $maxMonth * 100 }}%"></i>
                    </span>
                    <span class="mera-track-bar-value"><b>{{ number_format($m['started']) }}</b> <small>acum. {{ round($m['pct_cumulative']) }} %</small></span>
                </li>
                @endforeach
            </ol>
            @endif
        </div>
    </section>

    <section class="card mera-table-card" aria-labelledby="t-eval">
        <div class="card-body">
            <h2 id="t-eval" class="h6 mera-track-title">Evaluación</h2>
            @if ($bands['n'] === 0)
            <p class="text-muted small mb-0">Nadie ha presentado la evaluación con estos filtros.</p>
            @else
            <p class="small text-muted">{{ number_format($bands['n']) }} evaluaciones presentadas.</p>
            <ol class="mera-track-months list-unstyled mb-0">
                @foreach (['b90' => '90 a 100', 'b80' => '80 a 89', 'b70' => '70 a 79', 'b60' => '60 a 69', 'b0' => 'Menos de 60'] as $key => $label)
                <li>
                    <span class="mera-track-month">{{ $label }}</span>
                    <span class="mera-track-bar"><i class="{{ in_array($key, ['b90', 'b80'], true) ? 'is-passed' : 'is-failed' }}" style="width: {{ $bands[$key] / $bands['n'] * 100 }}%"></i></span>
                    <span class="mera-track-bar-value"><b>{{ round($bands[$key] / $bands['n'] * 100) }} %</b> <small>{{ number_format($bands[$key]) }}</small></span>
                </li>
                @endforeach
            </ol>
            <p class="small text-muted mt-2 mb-0">Aprobar pide 80 o más (90 en Corresponsal Cambiario): ver Catálogo de cursos.</p>
            @endif
        </div>
    </section>

    <section class="card mera-table-card" aria-labelledby="t-reprueban">
        <div class="card-body">
            <h2 id="t-reprueban" class="h6 mera-track-title">Dónde reprueban más</h2>
            <p class="small text-muted">% de reprobados entre quienes presentaron (localidades con al menos {{ config('mera.progress.dashboard.failing_min_graded') }} evaluaciones{{ $onlyGenerals ? ', cursos generales' : '' }}).</p>
            @if ($failing->isEmpty())
            <p class="text-muted small mb-0">Sin datos con estos filtros.</p>
            @else
            <ol class="mera-track-months list-unstyled mb-0">
                @foreach ($failing->take(10) as $r)
                <li>
                    <span class="mera-track-month"><a href="{{ route('progress.people', ['report' => $report] + $base + ['estado' => 'reprobados', 'localidad' => $r['label']]) }}">{{ $r['label'] }}</a></span>
                    <span class="mera-track-bar"><i class="is-failed" style="width: {{ $r['pct_failed'] }}%"></i></span>
                    <span class="mera-track-bar-value"><b>{{ round($r['pct_failed']) }} %</b> <small>{{ number_format($r['failed']) }} de {{ number_format($r['done']) }}</small></span>
                </li>
                @endforeach
            </ol>
            @endif
        </div>
    </section>
</div>

@include('progress._surveys', ['labels' => $d->courseLabels()])

<section class="card mera-table-card mt-3" aria-labelledby="t-especificos">
    <div class="card-body">
        <h2 id="t-especificos" class="h6 mera-track-title">Cursos específicos y presenciales</h2>
        <p class="small text-muted">
            Cursos para ciertas áreas o puestos, y los presenciales (BL). No se comparan entre localidades: cada uno muestra a quién va.
            En un BL, el avance mide las <strong>actividades en línea</strong>, no la asistencia presencial.
        </p>
        @if ($specific->isEmpty())
        <p class="text-muted small mb-0">No hay cursos específicos ni presenciales con estos filtros.</p>
        @else
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack mb-0">
                <thead>
                    <tr>
                        <th scope="col">Curso</th>
                        <th scope="col">A quién va</th>
                        <th scope="col">Realizado</th>
                        <th scope="col">Aprobado</th>
                        <th scope="col">Sin iniciar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($specific as $s)
                    <tr>
                        <td data-label="Curso">
                            <a href="{{ $with(['curso' => $s['k']]) }}" class="user-name">{{ $s['label'] }}</a>
                            <span class="d-block mt-1">
                                @if ($s['specific'])<span class="mera-scope-badge is-specific">Específico</span>@endif
                                <span class="mera-scope-badge {{ $s['modality'] === 'BL' ? 'is-bl' : '' }}">{{ $s['modality'] === 'BL' ? 'BL · presencial' : $s['modality'] }}</span>
                            </span>
                            <small class="d-block text-muted">{{ number_format($s['assigned']) }} asignados</small>
                        </td>
                        <td data-label="A quién va">
                            <small class="d-block">{{ collect($s['areas'])->map(fn ($n, $a) => $a.' ('.number_format($n).')')->join(', ') }}</small>
                            <small class="d-block text-muted">{{ collect($s['positions'])->map(fn ($n, $p) => $p.' ('.number_format($n).')')->join(', ') }}</small>
                        </td>
                        <td data-label="Realizado" class="mera-nowrap">{{ round($s['pct_done']) }} %</td>
                        <td data-label="Aprobado" class="mera-nowrap">{{ round($s['pct_passed']) }} %</td>
                        <td data-label="Sin iniciar" class="mera-nowrap">{{ round($s['pct_not_started']) }} %</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</section>
@endif
@endsection
