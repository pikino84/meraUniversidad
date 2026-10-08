@extends('layouts.app')

@section('title', 'Editar usuario')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-user" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Editar usuario</h1>
            <span>{{ $user->email }}</span>
        </div>
    </div>
    <div class="card-block">
        @include('users._form', ['action' => route('users.update', $user), 'method' => 'PUT', 'submitLabel' => 'Guardar cambios'])
    </div>
</div>
@endsection
