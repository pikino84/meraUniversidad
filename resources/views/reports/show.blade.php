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
<div class="mera-report-actions mb-3">
    @php($version = $report->version()->with('market')->first())
    <a href="{{ $version ? route('course-groups.versions.show', [$group, $version, 'seccion' => 'reportes']) : route('course-groups.show', $group) }}" class="btn mera-btn-cancel btn-sm"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ $group->name }}{{ $version ? ' · '.$version->label() : '' }}</a>
    <a href="{{ route('course-groups.reports.edit', [$group, $report]) }}" class="btn mera-btn-cancel btn-sm"><i class="fas fa-pencil-alt" aria-hidden="true"></i> Actualizar corte</a>
    <a href="{{ route('course-groups.reports.download', [$group, $report]) }}" class="btn mera-btn-cancel btn-sm" data-no-loader>
        <i class="fas fa-download" aria-hidden="true"></i> Descargar HTML
    </a>
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

<div class="mt-4">
@include('reports._dashboard', ['report' => $report])
</div>

<p class="small text-muted mt-4">
    Actualizado el {{ $report->updated_at->timezone($tz)->format('d/m/Y H:i') }}.
    Solo incluye totales por localidad, área y puesto: ningún nombre ni número de empleado.
</p>
@endsection
