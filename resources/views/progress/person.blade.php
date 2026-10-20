@extends('layouts.app')

@section('title', $person->full_name.' · Seguimiento anual')

@section('content')
@php
    $by = $items->countBy('status');
    $done = ($by[\App\Models\ProgressReportItem::STATUS_PASSED] ?? 0) + ($by[\App\Models\ProgressReportItem::STATUS_FAILED] ?? 0);
    $graded = $items->filter(fn ($i) => $i->grade !== null && in_array($i->status, [1, 2], true));
    $hours = $items->sum('hours');
    $hoursReal = $items->sum(fn ($i) => $i->hours * $i->progress);
    $badge = [1 => 'mera-status-on', 2 => 'mera-track-badge-failed', 3 => 'mera-track-badge-progress', 4 => 'mera-status-off'];
@endphp

<x-folder-header :crumbs="[['Seguimiento anual', route('progress.index')], [$report->cut_date->format('d/m/Y'), route('progress.show', $report)], ['Colaboradores', route('progress.people', $report)], [$person->full_name, null]]"
    :title="$person->full_name" :subtitle="'No. '.$person->employee_number.' · '.$person->position.' · '.$person->area.' · '.$person->locality.($person->work_center ? ' · '.$person->work_center : '')">
    <button type="button" class="btn btn-sm mera-btn-ghost" data-print><i class="fas fa-print" aria-hidden="true"></i> Imprimir</button>
</x-folder-header>

<section class="mera-track-cards" aria-label="Resumen de la persona">
    <div class="mera-track-card"><span>Cursos asignados</span><b>{{ $items->count() }}</b><small>que cuentan para el seguimiento</small></div>
    <div class="mera-track-card is-main"><span>Realizados</span><b>{{ $done }}</b><small>{{ $by[1] ?? 0 }} aprobados · {{ $by[2] ?? 0 }} reprobados</small></div>
    <div class="mera-track-card"><span>Pendientes</span><b>{{ ($by[3] ?? 0) + ($by[4] ?? 0) }}</b><small>{{ $by[3] ?? 0 }} en curso · {{ $by[4] ?? 0 }} sin iniciar</small></div>
    <div class="mera-track-card"><span>Calificación promedio</span><b>{{ $graded->isNotEmpty() ? round($graded->avg('grade'), 1) : '—' }}</b><small>de sus evaluaciones</small></div>
    <div class="mera-track-card"><span>Horas</span><b>{{ round($hoursReal, 1) }} h</b><small>de {{ round($hours, 1) }} h asignadas</small></div>
</section>

<section class="card mera-table-card" aria-labelledby="t-cursos-persona">
    <div class="card-body">
        <h2 id="t-cursos-persona" class="h6 mera-track-title">Sus cursos</h2>
        @if ($items->isEmpty())
        <p class="text-muted mb-0">No tiene cursos que cuenten para el seguimiento en este corte.</p>
        @else
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack mb-0">
                <thead>
                    <tr>
                        <th scope="col">Curso</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Avance</th>
                        <th scope="col">Calificación</th>
                        <th scope="col">Inició · Terminó</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                    <tr>
                        <td data-label="Curso">{{ $labels[$item->course_code] ?? $item->course_code }}<small class="d-block text-muted">{{ $item->course_code }} · {{ $item->year }}</small></td>
                        <td data-label="Estado"><span class="mera-status {{ $badge[$item->status] ?? '' }}"><i class="fas fa-circle" aria-hidden="true"></i> {{ $item->statusLabel() }}</span></td>
                        <td data-label="Avance">{{ round($item->progress * 100) }} %</td>
                        <td data-label="Calificación">
                            {{ $item->grade !== null ? rtrim(rtrim(number_format($item->grade, 2), '0'), '.') : '—' }}
                            @if ($item->catalogCourse && $item->catalogCourse->has_evaluation)<small class="d-block text-muted">aprueba con {{ $item->catalogCourse->passing_grade }}</small>@endif
                        </td>
                        <td data-label="Inició · Terminó" class="mera-nowrap">{{ $item->started_at?->format('d/m/Y') ?? '—' }} · {{ $item->completed_at?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</section>

@if ($history->count() > 1)
<section class="card mera-table-card mt-3" aria-labelledby="t-historial">
    <div class="card-body">
        <h2 id="t-historial" class="h6 mera-track-title">En cortes anteriores</h2>
        <ul class="list-unstyled mb-0 mera-track-history">
            @foreach ($history as $h)
            <li>
                <a href="{{ route('progress.show', $h->id) }}">{{ \Illuminate\Support\Carbon::parse($h->cut_date)->format('d/m/Y') }}</a>
                · {{ $h->done }} de {{ $h->assigned }} realizados ({{ $h->assigned ? round($h->done / $h->assigned * 100) : 0 }} %) · {{ $h->passed }} aprobados
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif

<p class="mt-3"><a href="{{ $back }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Regresar</a></p>
@endsection
