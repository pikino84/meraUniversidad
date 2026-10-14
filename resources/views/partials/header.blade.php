@php
    $authUser = Auth::user();
    $roleLabel = $authUser->primaryRoleLabel();
@endphp
<!-- [ Header ] start -->
<nav class="navbar header-navbar pcoded-header" aria-label="Barra superior">
    <div class="navbar-wrapper">
        <div class="navbar-logo">
            <a class="mobile-menu" id="mobile-collapse" href="#!" aria-label="Mostrar u ocultar menú">
                <i class="feather icon-menu" aria-hidden="true"></i>
            </a>
            <a class="text-left" href="{{ $authUser->hasAnyRole(\App\Models\User::PANEL_ROLES) ? route('dashboard') : route('profile.edit') }}">
                <img class="img-fluid h-10" src="{{ asset('images/logo_university.svg') }}" width="110" height="40" alt="MERA University – Ir al inicio" />
            </a>
            <div class="mobile-user">
                <div class="dropdown-primary dropdown">
                    <button type="button" class="dropdown-toggle mera-user-toggle btn p-0 border-0 bg-transparent"
                        data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de usuario">
                        <img src="{{ asset('images/avatar-blank.jpg') }}" class="img-radius mera-avatar" alt="">
                        <i class="feather icon-chevron-down" aria-hidden="true"></i>
                    </button>
                    @include('partials.user-menu', ['menuClass' => 'profile_mobile'])
                </div>
            </div>
        </div>

        <div class="navbar-container container-fluid">
            <ul class="nav-left">
                {{-- Auditoría UI 2026-10-14 (#17): se quitó el botón de pantalla completa (sin utilidad en el panel). --}}
                <li class="mera-header-title">
                    <span>Bienvenido, {{ $authUser->name }}</span>
                </li>
            </ul>
            <ul class="nav-right">
                <li class="user-profile header-notification">
                    <div class="dropdown-primary dropdown">
                        <button type="button" class="dropdown-toggle mera-user-toggle btn p-0 border-0 bg-transparent text-start"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="{{ asset('images/avatar-blank.jpg') }}" class="img-radius mera-avatar" alt="">
                            <div class="mera-user-info">
                                <span class="mera-user-name">{{ $authUser->name }}</span>
                                <small class="mera-user-role">{{ $roleLabel }}</small>
                            </div>
                            <i class="feather icon-chevron-down" aria-hidden="true"></i>
                        </button>
                        @include('partials.user-menu', ['menuClass' => ''])
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- [ Header ] end -->
