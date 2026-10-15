@extends('layouts.app')

@section('title', 'Nuevo corte de reporte')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-chart-bar" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Nuevo corte de reporte</h1>
            <span>{{ $group->name }}</span>
        </div>
    </div>
    <div class="card-block">
        @include('reports._form', ['action' => route('course-groups.reports.store', $group), 'method' => 'POST', 'submitLabel' => 'Crear corte'])
    </div>
</div>
@endsection
