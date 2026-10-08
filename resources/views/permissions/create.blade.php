@extends('layouts.app')

@section('title', 'Nuevo permiso')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-key" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Crear permiso</h1>
            <span>Registra un nuevo permiso en el sistema</span>
        </div>
    </div>
    <div class="card-block">
        @include('permissions._form', ['action' => route('permissions.store'), 'method' => 'POST', 'submitLabel' => 'Crear permiso'])
    </div>
</div>
@endsection
