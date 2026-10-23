{{-- Corte de reporte. $shared = true: vista pública del link para compartir (sin sesión, noindex,
     sin acciones). Variables: $group, $report, $shared --}}
@extends($shared ? 'layouts.public' : 'layouts.app')

@section('title', $report->title)

@push($shared ? 'head' : 'styles')
@if ($shared)
<meta name="robots" content="noindex, nofollow">
@endif
@vite(['resources/css/report.css', 'resources/js/report-dashboard.js'])
@endpush

@section('content')
@php $tz = config('mera.display_timezone'); @endphp

@include('partials.page-header', [
    'title' => $report->title,
    'subtitle' => ($shared ? '' : $group->name.' · ').'Corte al '.$report->cut_date->translatedFormat('j \d\e F \d\e Y'),
])

@unless ($shared)
@php
    $version = $report->version()->with('market')->first();
    // Rutina: comentarios por clasificar (ya hay botón) e inscritos omitidos. Lo demás (p. ej. una pregunta que
    // no viene) sí pide atención y abre el panel.
    $notices = collect($report->warnings ?? []);
    $important = $notices->reject(fn ($w) => str_contains($w, 'sin clasificar') || str_contains($w, '(se omiten)'));
@endphp
{{-- Acciones (2026-10-23: menos ruido). A la vista: volver y lo que hay que hacer; lo demás, en "Más acciones". --}}
<div class="mera-report-toolbar mb-3">
    <a href="{{ $version ? route('course-groups.versions.show', [$group, $version, 'seccion' => 'reportes']) : route('course-groups.show', $group) }}" class="btn mera-btn-cancel btn-sm"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ $group->name }}{{ $version ? ' · '.$version->label() : '' }}</a>

    <span class="mera-report-toolbar-end">
        @if ($report->pendingCount())
        <a href="{{ route('course-groups.reports.pending', [$group, $report]) }}" class="btn mera-btn-primary btn-sm">
            <i class="fas fa-tags" aria-hidden="true"></i> Clasificar comentarios <span class="mera-report-count">{{ number_format($report->pendingCount()) }}</span>
        </a>
        @endif
        <details class="mera-report-more">
            <summary class="btn mera-btn-cancel btn-sm"><i class="fas fa-ellipsis-h" aria-hidden="true"></i> Más acciones</summary>
            <div class="mera-report-menu">
                <a href="{{ route('course-groups.reports.edit', [$group, $report]) }}"><i class="fas fa-pencil-alt" aria-hidden="true"></i> Actualizar corte</a>
                <a href="{{ route('course-groups.reports.download', [$group, $report]) }}" data-no-loader><i class="fas fa-download" aria-hidden="true"></i> Descargar HTML</a>
                <a href="#compartir-reporte" data-open-details="compartir"><i class="fas fa-share-alt" aria-hidden="true"></i> Compartir sin sesión</a>
                <form method="POST" action="{{ route('course-groups.reports.destroy', [$group, $report]) }}"
                    data-confirm="¿Eliminar este corte?" data-confirm-strong="{{ $report->title }} · {{ $report->cut_date->format('d/m/Y') }}"
                    data-confirm-detail="Se borran sus datos y su link para compartir deja de funcionar. Los demás cortes no cambian." data-confirm-tone="danger">
                    @csrf @method('DELETE')
                    <button type="submit" class="is-danger"><i class="fas fa-trash-alt" aria-hidden="true"></i> Eliminar corte</button>
                </form>
            </div>
        </details>
    </span>
</div>

<div class="mera-report-panels">
    @if ($notices->isNotEmpty())
    <details class="mera-report-panel is-warning" {{ $important->isNotEmpty() ? 'open' : '' }}>
        <summary>
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            <strong>{{ $notices->count() === 1 ? '1 aviso' : $notices->count().' avisos' }} del procesamiento</strong>
            @if ($important->isEmpty())<small>Informativos: nada que corregir.</small>@endif
        </summary>
        <ul class="mb-0">
            @foreach ($notices as $warning)
            <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </details>
    @endif

    <details class="mera-report-panel" id="compartir" @if (session('success') && str_contains(strtolower((string) session('success')), 'link')) open @endif>
        <summary>
            <i class="fas fa-share-alt" aria-hidden="true"></i>
            <strong>Compartir sin sesión</strong>
            @if ($report->isShared())
            <span class="mera-status mera-status-on"><i class="fas fa-circle" aria-hidden="true"></i> Link activo</span>
            @else
            <span class="mera-status mera-status-off"><i class="fas fa-circle" aria-hidden="true"></i> Sin link</span>
            @endif
        </summary>
        @include('reports._share', ['group' => $group, 'report' => $report, 'compact' => true])
    </details>
</div>
@endunless

<div class="mt-4">
@include('reports._dashboard', ['report' => $report])
</div>

<p class="small text-muted mt-4">
    Actualizado el {{ $report->updated_at->timezone($tz)->format('d/m/Y H:i') }}.
    Solo incluye totales por localidad, área y puesto: ningún nombre ni número de empleado.
</p>
@endsection
