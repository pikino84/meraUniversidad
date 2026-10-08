<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('description', 'Catálogo de cursos de ' . config('app.name'))">
    <title>@yield('title', 'Cursos') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/favicon.ico') }}" type="image/x-icon">

    @include('partials.brand-fonts')
    <link rel="stylesheet" href="{{ asset('bower_components/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/font-awesome-n.min.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-light">
    <header class="bg-white border-bottom">
        <div class="container d-flex justify-content-between align-items-center py-3">
            <a href="{{ route('catalog.index') }}">
                <img src="{{ asset('images/logo_university.svg') }}" alt="{{ config('app.name') }}" class="mera-public-logo">
            </a>
            <a href="{{ route('login') }}" class="btn btn-sm mera-btn-primary">Acceso administradores</a>
        </div>
    </header>

    <main id="main-content" class="container py-4">
        @yield('content')
    </main>
</body>

</html>
