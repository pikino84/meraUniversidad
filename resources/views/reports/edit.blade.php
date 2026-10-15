@extends('layouts.app')

@section('title', 'Actualizar corte')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-chart-bar" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Actualizar corte</h1>
            <span>{{ $group->name }} · {{ $report->title }}</span>
        </div>
    </div>
    <div class="card-block">
        @include('reports._form', ['action' => route('course-groups.reports.update', [$group, $report]), 'method' => 'PUT', 'submitLabel' => 'Guardar cambios'])
    </div>
</div>
@endsection
