@extends('layouts.app')

@section('title', 'Nuevo usuario')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-user-plus" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Crear usuario</h1>
            <span>Registra un nuevo usuario en el sistema</span>
        </div>
    </div>
    <div class="card-block">
        @include('users._form', ['action' => route('users.store'), 'method' => 'POST', 'submitLabel' => 'Crear usuario'])
    </div>
</div>
@endsection
