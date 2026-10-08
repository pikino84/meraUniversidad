<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        <meta name="color-scheme" content="light">

        <title>{{ $title ?? 'Acceso' }} · {{ config('app.name') }}</title>
        <link rel="icon" href="{{ asset('images/favicon.ico') }}" type="image/x-icon">

        @include('partials.brand-fonts')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-800 antialiased">
        <div class="mera-auth">
            <aside class="mera-auth-brand" aria-hidden="true">
                <div style="position:relative;z-index:1">
                    <h1>MERA University</h1>
                    <p>Plataforma de capacitación de MERA. Administra cursos, categorías y usuarios desde un solo lugar.</p>
                </div>
                <div class="mera-auth-tagline">Vision · Values · Results</div>
            </aside>

            <main class="mera-auth-panel">
                <div class="mera-auth-card">
                    <a href="{{ url('/') }}">
                        <img class="mera-auth-logo" src="{{ asset('images/logo_university.svg') }}" alt="{{ config('app.name') }}">
                    </a>
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
