<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    {{-- Sin user-scalable=0: el usuario debe poder hacer zoom (WCAG 1.4.4) --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Panel de administración de {{ config('app.name') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Panel') · {{ config('app.name') }}</title>

    @include('partials.favicon')

    @include('partials.brand-fonts')

    {{-- Plantilla (Bootstrap 5). P10: sin waves, widget.css, jQuery UI ni script.js (ver resources/js/modules/panel-template.js) --}}
    <link rel="stylesheet" href="{{ asset('bower_components/bootstrap/css/bootstrap.min.css') }}">
    {{-- U16: precarga de las fuentes de iconos para que no se vean como cuadros (□) al cargar.
         La URL de feather lleva el mismo ?t= que su CSS para que el navegador reutilice la descarga. --}}
    <link rel="preload" href="{{ asset('fonts/fa-solid-900.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('icon/feather/fonts/feather.woff') }}?t=1501841394106" as="font" type="font/woff" crossorigin>
    <link rel="stylesheet" href="{{ asset('icon/feather/css/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('css/font-awesome-n.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">

    <!-- Estilos y JS propios (Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="font-sans antialiased">
    <a href="#main-content" class="visually-hidden-focusable">Saltar al contenido</a>

    {{-- P12: se quitó el preloader de la plantilla (.loader-bg): atenuaba cada página ~1 s aunque ya hubiera cargado. --}}

    <div id="pcoded" class="pcoded">
        <div class="pcoded-overlay-box"></div>
        <div class="pcoded-container navbar-wrapper">
            @include('partials.header')
            <div class="pcoded-main-container">
                <div class="pcoded-wrapper">
                    @include('layouts.navigation')
                    <main id="main-content" class="pcoded-content px-4 py-4" tabindex="-1">
                        @yield('content')
                    </main>
                </div>
            </div>
        </div>
    </div>

    @include('partials.flash')

    <script src="{{ asset('bower_components/jquery/js/jquery.min.js') }}"></script>
    <script src="{{ asset('bower_components/popper.js/js/popper.min.js') }}"></script>
    <script src="{{ asset('bower_components/bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('bower_components/jquery-slimscroll/js/jquery.slimscroll.js') }}"></script>
    <script src="{{ asset('js/pcoded.min.js') }}"></script>
    <script src="{{ asset('js/vertical/vertical-layout.min.js') }}"></script>
    @stack('scripts')
</body>

</html>
