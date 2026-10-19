<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('description', 'Catálogo de cursos de ' . config('app.name'))">
    <title>@yield('title', 'Cursos') · {{ config('app.name') }}</title>
    @include('partials.favicon')

    @include('partials.brand-fonts')
    <link rel="stylesheet" href="{{ asset('bower_components/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/font-awesome-n.min.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body class="bg-light">
    @include('partials.page-loader')
    @include('partials.test-environment')
    <header class="bg-white border-bottom">
        <div class="container d-flex justify-content-between align-items-center gap-3 py-3">
            <a href="{{ route('catalog.index') }}" class="flex-shrink-0">
                <img src="{{ asset('images/logo_university.svg') }}" width="121" height="44" alt="MERA University – Catálogo de cursos" class="mera-public-logo">
            </a>
            {{-- Botón compacto: no crece ni se parte en dos líneas; en celulares angostos dice solo "Acceso". --}}
            <a href="{{ route('login') }}" class="btn mera-btn-primary mera-header-cta" aria-label="Acceso administradores">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <span>Acceso<span class="d-none d-sm-inline"> administradores</span></span>
            </a>
        </div>
    </header>

    <main id="main-content" class="container py-4">
        @yield('content')
    </main>
</body>

</html>
