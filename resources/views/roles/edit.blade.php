@extends('layouts.app')

@section('title', 'Editar rol')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-users" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Editar rol</h1>
            <span>Actualiza el nombre y los permisos del rol</span>
        </div>
    </div>
    <div class="card-block">
        @include('roles.form', ['action' => route('roles.update', $role), 'method' => 'PUT', 'submitLabel' => 'Guardar cambios'])
    </div>
</div>
@endsection
