@extends('layouts.app')

@section('title', $report->title.' · Seguimiento anual')

@section('content')
@php
    $base = array_filter($filters);
    $with = fn (array $changes) => route('progress.show', array_filter(array_merge(['report' => $report] + $base, $changes)));
    $t = $totals;
    $monthNames = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr', '05' => 'may', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic'];
    $maxMonth = max(1, $months->max('started') ?? 1);
@endphp

<x-folder-header :crumbs="[['Seguimiento anual', route('progress.index')], [$report->cut_date->format('d/m/Y'), null]]"
    :title="$report->title" :subtitle="'Corte al '.$report->cut_date->translatedFormat('j \d\e F \d\e Y').' · todos los cursos que cuentan del año'">
    <a href="{{ route('progress.people', ['report' => $report] + $base) }}" class="btn btn-sm mera-btn-ghost"><i class="fas fa-users" aria-hidden="true"></i> Consulta de colaboradores</a>
</x-folder-header>

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
        <small>{{ number_format($t['assigned']) }} asignaciones · {{ $t['courses_per_person'] }} cursos por persona</small>
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
        <span>Horas por persona</span><b>{{ $t['hours_real_per_person'] }} h</b>
        <small>de {{ $t['hours_per_person'] }} h asignadas ({{ $t['pct_hours'] }} %)</small>
    </div>
</section>

<p class="mera-track-legend small text-muted" aria-hidden="true">
    <i class="is-passed"></i> Aprobado <i class="is-failed"></i> Reprobado <i class="is-progress"></i> En curso <i class="is-empty"></i> Sin iniciar
    · el % es lo realizado sobre lo asignado
</p>

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
            <p class="small text-muted">Localidades con al menos {{ config('mera.progress.dashboard.ranking_min_people') }} personas, por % realizado.</p>
            @include('progress._bars', ['rows' => $localities, 'numbered' => true, 'link' => $filters['localidad'] ? null : fn ($l) => $with(['localidad' => $l])])
        </div>
    </section>

    <section class="card mera-table-card" aria-labelledby="t-areas">
        <div class="card-body">
            <h2 id="t-areas" class="h6 mera-track-title">Avance por área</h2>
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
            <p class="small text-muted">% de reprobados entre quienes presentaron (localidades con al menos {{ config('mera.progress.dashboard.failing_min_graded') }} evaluaciones).</p>
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
@endif
@endsection
