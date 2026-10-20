@extends('layouts.app')

@section('title', 'Consulta de colaboradores · '.$report->title)

@section('content')
@php
    $base = array_filter($filters);
    $labels = $d->courseLabels();
    $titles = ['reprobados' => 'Reprobados', 'sin-iniciar' => 'Sin iniciar', 'en-curso' => 'En curso', 'aprobados' => 'Aprobados'];
    $hints = [
        'reprobados' => 'Vieron el curso completo y sacaron menos de la calificación mínima.',
        'sin-iniciar' => 'Tienen el curso asignado y no lo han empezado.',
        'en-curso' => 'Lo empezaron y no lo han terminado, o no han presentado la evaluación.',
        'aprobados' => 'Vieron el curso completo y aprobaron la evaluación.',
    ];
@endphp

<x-folder-header :crumbs="[['Seguimiento anual', route('progress.index')], [$report->cut_date->format('d/m/Y'), route('progress.show', ['report' => $report] + $base)], ['Colaboradores', null]]"
    title="Consulta de colaboradores" :subtitle="$report->title.' · corte al '.$report->cut_date->format('d/m/Y')">
    <a href="{{ route('progress.export', ['report' => $report, 'estado' => $statusKey, 'q' => $search] + $base) }}" class="btn btn-sm mera-btn-ghost" data-no-loader>
        <i class="fas fa-download" aria-hidden="true"></i> Descargar CSV
    </a>
</x-folder-header>

@include('progress._filters', ['action' => route('progress.people', $report), 'filters' => $filters, 'options' => $options, 'extra' => ['estado' => $statusKey, 'q' => $search]])

<nav class="mera-section-tabs" aria-label="Estado">
    @foreach ($titles as $key => $title)
    <a href="{{ route('progress.people', ['report' => $report, 'estado' => $key, 'q' => $search] + $base) }}"
        class="mera-section-tab {{ $statusKey === $key ? 'is-active' : '' }}" @if ($statusKey === $key) aria-current="page" @endif>
        <span>{{ $title }}</span> <span class="mera-section-count">{{ number_format($counts[$key]) }}</span>
    </a>
    @endforeach
</nav>

<section class="card mera-table-card">
    <div class="card-body">
        <div class="mera-section-toolbar">
            <p class="small text-muted mb-0">{{ $hints[$statusKey] }} Una persona aparece una vez por curso.</p>
            <form method="GET" action="{{ route('progress.people', $report) }}" class="d-flex gap-2" role="search">
                @foreach ($base + ['estado' => $statusKey] as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                <label class="visually-hidden" for="p-q">Buscar por nombre o número de empleado</label>
                <input id="p-q" type="search" name="q" value="{{ $search }}" class="form-control mera-input" placeholder="Nombre o No. de empleado">
                <button type="submit" class="btn btn-sm mera-btn-save">Buscar</button>
            </form>
        </div>

        @if ($rows->isEmpty())
        <div class="mera-empty-state">
            <i class="fas fa-users" aria-hidden="true"></i>
            <strong>Nadie en esta lista</strong>
            <span>Prueba con otros filtros o con otra pestaña.</span>
        </div>
        @else
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack table-hover mb-0">
                <thead>
                    <tr>
                        <th scope="col">Persona</th>
                        <th scope="col">Localidad · Área</th>
                        <th scope="col">Curso</th>
                        <th scope="col">Avance</th>
                        <th scope="col">Calificación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                    <tr>
                        <td data-label="Persona">
                            <a href="{{ route('progress.person', ['report' => $report, 'person' => $r->person_id] + $base) }}" class="user-name">{{ $r->full_name }}</a>
                            <small class="d-block text-muted">No. {{ $r->employee_number }} · {{ $r->position }}</small>
                        </td>
                        <td data-label="Localidad · Área">{{ $r->locality }}<small class="d-block text-muted">{{ $r->area }}@if ($r->work_center) · {{ $r->work_center }}@endif</small></td>
                        <td data-label="Curso">{{ $labels[$r->course_code] ?? $r->course_code }}<small class="d-block text-muted">{{ $r->course_code }}</small></td>
                        <td data-label="Avance" class="mera-nowrap">{{ round($r->progress * 100) }} %</td>
                        <td data-label="Calificación">{{ $r->grade !== null ? rtrim(rtrim(number_format($r->grade, 2), '0'), '.') : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</section>

@if ($rows->hasPages())
<div class="mt-3">{{ $rows->links() }}</div>
@endif
@endsection
