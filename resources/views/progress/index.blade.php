@extends('layouts.app')

@section('title', 'Seguimiento anual')

@section('content')
@include('partials.page-header', [
    'title' => 'Seguimiento anual',
    'subtitle' => 'Avance de todas las personas en todos los cursos del año, por corte.',
    'action' => ['url' => route('progress.create'), 'label' => 'Nuevo corte'],
])

<div class="card mera-table-card">
    <div class="card-body">
        @if ($reports->isEmpty())
        <div class="mera-empty-state">
            <i class="fas fa-chart-line" aria-hidden="true"></i>
            <strong>Todavía no hay cortes</strong>
            <span>Sube el «Reporte de progreso de capacitaciones» y el «Reporte de personal» para crear el primero.</span>
        </div>
        @else
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack table-hover mb-0">
                <thead>
                    <tr>
                        <th scope="col">Corte</th>
                        <th scope="col">Personas</th>
                        <th scope="col">Asignaciones</th>
                        <th scope="col">Avisos</th>
                        <th scope="col" class="text-center mera-col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reports as $report)
                    @php($s = $report->summary ?? [])
                    <tr>
                        <td data-label="Corte">
                            <a href="{{ route('progress.show', $report) }}" class="user-name">{{ $report->cut_date->format('d/m/Y') }}</a>
                            <small class="d-block text-muted">{{ $report->title }}@if ($report->creator) · {{ $report->creator->name }}@endif</small>
                            @if ($loop->first)<span class="mera-status mera-status-on mt-1"><i class="fas fa-circle" aria-hidden="true"></i> Más reciente</span>@endif
                        </td>
                        <td data-label="Personas">{{ number_format($s['people'] ?? 0) }}</td>
                        <td data-label="Asignaciones">{{ number_format($s['items'] ?? 0) }}</td>
                        <td data-label="Avisos">{{ count($report->warnings ?? []) ?: '—' }}</td>
                        <td data-label="Acciones" class="text-center text-nowrap mera-col-actions">
                            <a href="{{ route('progress.show', $report) }}" class="mera-action-btn mera-edit-btn" title="Ver" aria-label="Ver el corte del {{ $report->cut_date->format('d/m/Y') }}">
                                <i class="fa fa-eye" aria-hidden="true"></i>
                            </a>
                            @include('partials.delete-button', [
                                'action' => route('progress.destroy', $report),
                                'label' => 'Eliminar el corte del '.$report->cut_date->format('d/m/Y'),
                                'confirm' => '¿Eliminar el corte?',
                                'strong' => $report->title.' · '.$report->cut_date->format('d/m/Y'),
                                'detail' => 'Se borran sus cifras y su lista de personas. Los Excel no se guardan: para recuperarlo hay que volver a subirlos.',
                            ])
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-muted mt-3 mb-0">Se conservan los últimos {{ config('mera.progress.keep_reports') }} cortes; al crear uno nuevo se borra el más antiguo.</p>
        @endif
    </div>
</div>
@endsection
