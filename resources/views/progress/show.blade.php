@extends($share ? 'layouts.public' : 'layouts.app')

@section('title', $report->title.' · Seguimiento anual')

@section('content')
@php
    // Tablero del seguimiento anual, rediseñado (2026-10-20): filtros compactos, 4 indicadores y una
    // pestaña a la vez (?vista=). Los links conservan filtros y pestaña.
    $base = array_filter($filters);
    if ($share && $share->locality !== null) {
        unset($base['localidad']); // fija en el link compartido: no viaja en la URL
    }
    // $url arma el link del panel o del link compartido (ProgressReportController::dashboardData).
    $with = fn (array $changes) => $url(array_filter(array_merge($base + ['vista' => $view], $changes), fn ($v) => $v !== null && $v !== ''));
    $t = $totals;
    $c = $comparable;
    $onlyGenerals = $d->generalsOnly();
@endphp

@if ($share)
<div class="container py-4">
<div class="page-header mera-page-header mera-folder-header">
    <div class="header-title-wrapper">
        <span class="header-line" aria-hidden="true"></span>
        <div>
            <h1 class="mera-title h5">{{ $report->title }}{{ $share->locality ? ' · '.$share->locality : '' }}</h1>
            <p class="mera-subtitle">Corte al {{ $report->cut_date->translatedFormat('j \d\e F \d\e Y') }} · {{ number_format($t['people']) }} personas · seguimiento de capacitación</p>
        </div>
    </div>
</div>
@else
<x-folder-header :crumbs="[['Seguimiento anual', route('progress.index')], [$report->cut_date->format('d/m/Y'), null]]"
    :title="$report->title"
    :subtitle="'Corte al '.$report->cut_date->translatedFormat('j \d\e F \d\e Y').' · '.number_format($t['people']).' personas · '.count($options['courses']).' cursos que cuentan'">
    <a href="{{ route('progress.people', ['report' => $report] + $base) }}" class="btn btn-sm mera-btn-ghost"><i class="fas fa-users" aria-hidden="true"></i> Colaboradores</a>
    <a href="{{ route('progress.shares', $report) }}" class="btn btn-sm mera-btn-ghost"><i class="fas fa-link" aria-hidden="true"></i> Compartir{{ $shares ? ' ('.$shares.')' : '' }}</a>
    <a href="{{ route('progress.edit', $report) }}" class="btn btn-sm mera-btn-ghost"><i class="fas fa-pencil-alt" aria-hidden="true"></i> Editar</a>
</x-folder-header>
@endif

@if ($report->warnings && ! $share)
<details class="mera-track-warnings mb-3">
    <summary><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> {{ count($report->warnings) }} {{ count($report->warnings) === 1 ? 'aviso' : 'avisos' }} al crear este corte</summary>
    <ul class="mb-0 mt-2 small">
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

@include('progress._filters', ['action' => $url([]), 'filters' => $filters, 'options' => $options, 'extra' => ['vista' => $view, 'orden' => $view === 'cursos' ? $sort : null], 'lockedLocality' => $share?->locality])

@if ($courseVersions->isNotEmpty())
<p class="mera-track-scope-note small">
    <i class="fas fa-folder-open" aria-hidden="true"></i> Este curso en las carpetas:
    @foreach ($courseVersions as $v)
    <a href="{{ route('course-groups.versions.show', [$v->group, $v, 'seccion' => 'reportes']) }}">{{ $v->group->name }} · {{ $v->label() }}</a>@if (! $loop->last), @endif
    @endforeach
    (ahí están sus cortes por curso).
</p>
@endif

{{-- Indicadores: lo realizado y aprobado es de sus cursos asignados. --}}
<section class="mera-kpis" aria-label="Indicadores">
    <div class="mera-kpi is-main">
        <span class="mera-kpi-label">Realizado</span>
        <b class="mera-kpi-value">{{ $t['pct_done'] }} %</b>
        <span class="mera-kpi-hint">{{ number_format($t['done']) }} de {{ number_format($t['assigned']) }} cursos asignados, vistos completos y con evaluación</span>
    </div>
    <div class="mera-kpi">
        <span class="mera-kpi-label">Aprobado</span>
        <b class="mera-kpi-value">{{ $t['pct_passed'] }} %</b>
        <span class="mera-kpi-hint">{{ number_format($t['passed']) }} con la calificación mínima · {{ number_format($t['failed']) }} reprobados</span>
    </div>
    <div class="mera-kpi">
        <span class="mera-kpi-label">Sin iniciar</span>
        <b class="mera-kpi-value">{{ $t['pct_not_started'] }} %</b>
        <span class="mera-kpi-hint">{{ number_format($t['not_started']) }} sin empezar · {{ number_format($t['in_progress']) }} en curso</span>
    </div>
    <div class="mera-kpi">
        <span class="mera-kpi-label">Calificación promedio</span>
        <b class="mera-kpi-value">{{ $t['avg_grade'] ?? '—' }}</b>
        <span class="mera-kpi-hint">{{ $c['courses_per_person'] }} cursos y {{ $c['hours_real_per_person'] }} de {{ $c['hours_per_person'] }} h por persona{{ $onlyGenerals ? ' (generales)' : '' }}</span>
    </div>
</section>

<details class="mera-track-help small mb-3">
    <summary>¿Cómo se calcula?</summary>
    <ul class="mb-0 mt-2">
        <li><b>Realizado:</b> la persona vio el curso al 100 % y presentó la evaluación (con cualquier calificación). En cursos sin evaluación, basta el 100 %.</li>
        <li><b>Aprobado:</b> realizado y con la calificación mínima del curso (80; 90 en Corresponsal Cambiario). Se ajusta en el Catálogo de cursos.</li>
        <li><b>En curso:</b> empezó y no ha terminado, o terminó sin presentar la evaluación. <b>Sin iniciar:</b> 0 % de avance.</li>
        <li>Los porcentajes son sobre los cursos <b>asignados</b>. Solo cuentan los cursos marcados «Cuenta» en el catálogo.</li>
        @if ($onlyGenerals)<li>Las comparaciones entre localidades, áreas y puestos usan <b>solo cursos generales</b> (para todos); los específicos se ven en la pestaña Cursos. Se pueden incluir en «Más filtros».</li>@endif
    </ul>
</details>

<nav class="mera-section-tabs is-scroll" aria-label="Secciones del tablero">
    @foreach ($views as $key => $label)
    <a href="{{ $with(['vista' => $key, 'orden' => null]) }}" class="mera-section-tab {{ $view === $key ? 'is-active' : '' }}" @if ($view === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>

@include('progress.views.'.$view)
@endif

@if ($share)
<p class="small text-muted mt-4 mb-0">Vista compartida sin nombres de personas. Datos del corte al {{ $report->cut_date->format('d/m/Y') }}.</p>
</div>
@endif
@endsection
