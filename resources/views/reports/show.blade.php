{{-- Corte de reporte. $shared = true: vista pública del link para compartir (sin sesión, noindex,
     sin acciones). Variables: $group, $report, $shared --}}
@extends($shared ? 'layouts.public' : 'layouts.app')

@section('title', $report->title)

@if ($shared)
@push('head')
<meta name="robots" content="noindex, nofollow">
@endpush
@endif

@section('content')
@php
    $tz = config('mera.display_timezone');
    $s = $report->summary ?? [];
    $pct = fn ($v) => $v === null ? '—' : number_format(round($v)).' %';
    $kpis = [
        ['Asignados', number_format($s['asignados'] ?? 0), null, 'fas fa-users'],
        ['Completaron', number_format($s['completados'] ?? 0), $pct($s['pct_completado'] ?? null).' de los asignados', 'fas fa-check-circle'],
        ['Todavía no empiezan', number_format($s['sin_iniciar'] ?? 0), null, 'fas fa-hourglass-start'],
        ['Calificación promedio', isset($s['calif_promedio']) ? number_format($s['calif_promedio'], 1) : '—', $pct($s['pct_80'] ?? null).' sacó 80 o más', 'fas fa-star'],
        ['Satisfacción', $pct($s['satisfaccion'] ?? null), number_format($s['respuestas'] ?? 0).' respuestas', 'fas fa-smile'],
    ];
@endphp

@include('partials.page-header', [
    'title' => $report->title,
    'subtitle' => ($shared ? '' : $group->name.' · ').'Corte al '.$report->cut_date->translatedFormat('j \d\e F \d\e Y'),
])

@unless ($shared)
<div class="mera-report-actions mb-3">
    <a href="{{ route('course-groups.show', $group) }}" class="btn mera-btn-cancel btn-sm"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ $group->name }}</a>
    <a href="{{ route('course-groups.reports.edit', [$group, $report]) }}" class="btn mera-btn-cancel btn-sm"><i class="fas fa-pencil-alt" aria-hidden="true"></i> Actualizar corte</a>
    @if ($report->pendingCount())
    <a href="{{ route('course-groups.reports.pending', [$group, $report]) }}" class="btn mera-btn-primary btn-sm">
        <i class="fas fa-tags" aria-hidden="true"></i> Pendientes de clasificar ({{ $report->pendingCount() }})
    </a>
    @endif
    <form method="POST" action="{{ route('course-groups.reports.destroy', [$group, $report]) }}" class="d-inline"
        data-confirm="¿Eliminar este corte?" data-confirm-strong="{{ $report->title }} · {{ $report->cut_date->format('d/m/Y') }}"
        data-confirm-detail="Se borran sus datos y su link para compartir deja de funcionar. Los demás cortes no cambian." data-confirm-tone="danger">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-sm mera-btn-danger"><i class="fas fa-trash-alt" aria-hidden="true"></i> Eliminar corte</button>
    </form>
</div>
@endunless

{{-- Totales del corte (el tablero del paso 5 los recalcula con filtros) --}}
<div class="mera-report-kpis">
    @foreach ($kpis as [$label, $value, $note, $icon])
    <div class="mera-report-kpi">
        <i class="{{ $icon }}" aria-hidden="true"></i>
        <span class="mera-report-kpi-label">{{ $label }}</span>
        <strong>{{ $value }}</strong>
        @if ($note)<small>{{ $note }}</small>@endif
    </div>
    @endforeach
</div>

@if ($report->warnings && ! $shared)
<div class="alert alert-warning mt-3" role="status">
    <strong><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Avisos del procesamiento</strong>
    <ul class="mb-0 mt-1">
        @foreach ($report->warnings as $warning)
        <li>{{ $warning }}</li>
        @endforeach
    </ul>
</div>
@endif

@unless ($shared)
@include('reports._share', ['group' => $group, 'report' => $report])
@endunless

{{-- Tablero (gráficas y filtros): resources/js/modules/course-report.js lo monta aquí. --}}
<section class="mera-report mt-4" data-course-report aria-label="Tablero del reporte">
    <p class="text-muted small mb-0" data-report-placeholder>Cargando el tablero…</p>
</section>

{{-- Datos para el tablero. Nunca {{ }} dentro de <script>: JSON con escapes (CLAUDE.md §3). --}}
<script type="application/json" id="report-data">@json($report->data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)</script>

<p class="small text-muted mt-4">
    Actualizado el {{ $report->updated_at->timezone($tz)->format('d/m/Y H:i') }}.
    Solo incluye totales por localidad, área y puesto: ningún nombre ni número de empleado.
</p>
@endsection
