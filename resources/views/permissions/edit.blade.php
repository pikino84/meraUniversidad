@extends('layouts.app')

@section('title', 'Editar permiso')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-key" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Editar permiso</h1>
            <span>Actualiza la información del permiso seleccionado</span>
        </div>
    </div>
    <div class="card-block">
        @include('permissions._form', ['action' => route('permissions.update', $permission), 'method' => 'PUT', 'submitLabel' => 'Guardar cambios'])
    </div>
</div>
@endsection
