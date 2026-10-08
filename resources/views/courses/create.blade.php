@extends('layouts.app')

@section('title', 'Nuevo curso')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-book" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Crear curso</h1>
            <span>Registra un nuevo curso dentro del sistema</span>
        </div>
    </div>

    <div class="card-block">
        @include('courses._form', [
            'action' => route('courses.store'),
            'method' => 'POST',
            'submitLabel' => 'Crear curso',
        ])
    </div>
</div>
@endsection
