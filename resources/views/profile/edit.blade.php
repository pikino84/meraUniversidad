@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-user" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Mi perfil</h1>
            <span>Administra tu información personal y tu contraseña.</span>
        </div>
    </div>

    <div class="card-block">
        <div class="row">
            <div class="col-md-12">
                <div class="card border-1 shadow-sm mb-4">
                    <div class="card-body">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>
            </div>

            <div class="col-md-12">
                <div class="card border-1 shadow-sm mb-4">
                    <div class="card-body">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>
            </div>
        </div>

        {{-- S25: las cuentas las crea y las da de baja un administrador (Usuarios), no la propia persona. --}}
        <p class="text-muted small mb-0">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            ¿Necesitas dar de baja tu cuenta? Pídeselo a un administrador del sistema.
        </p>
    </div>
</div>
@endsection
