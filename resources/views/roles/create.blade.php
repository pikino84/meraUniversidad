@extends('layouts.app')

@section('title', 'Nuevo rol')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-users" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Crear rol</h1>
            <span>Registra un nuevo rol y asigna sus permisos</span>
        </div>
    </div>
    <div class="card-block">
        @include('roles.form', ['action' => route('roles.store'), 'method' => 'POST', 'submitLabel' => 'Crear rol'])
    </div>
</div>
@endsection
