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

    <link rel="icon" href="{{ asset('images/favicon.ico') }}" type="image/x-icon">

    @include('partials.brand-fonts')

    <!-- Plantilla (Bootstrap 5) -->
    <link rel="stylesheet" href="{{ asset('bower_components/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('pages/waves/css/waves.min.css') }}" media="all">
    <link rel="stylesheet" href="{{ asset('icon/feather/css/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('css/font-awesome-n.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/widget.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">

    <!-- Estilos y JS propios (Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="font-sans antialiased">
    <a href="#main-content" class="visually-hidden-focusable">Saltar al contenido</a>

    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-bar"></div>
    </div>
    <!-- [ Pre-loader ] end -->

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
    <script src="{{ asset('bower_components/jquery-ui/js/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('bower_components/popper.js/js/popper.min.js') }}"></script>
    <script src="{{ asset('bower_components/bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('pages/waves/js/waves.min.js') }}"></script>
    <script src="{{ asset('bower_components/jquery-slimscroll/js/jquery.slimscroll.js') }}"></script>
    <script src="{{ asset('js/pcoded.min.js') }}"></script>
    <script src="{{ asset('js/vertical/vertical-layout.min.js') }}"></script>
    <script src="{{ asset('js/script.min.js') }}"></script>
    @stack('scripts')
</body>

</html>
