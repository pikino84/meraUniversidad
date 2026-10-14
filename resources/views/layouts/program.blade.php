<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#024D25">
    <title>@yield('title') · MERA</title>
    <meta name="description" content="@yield('description')">

    {{-- Vista previa al compartir por WhatsApp/Teams/correo --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="MERA University">
    <meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_US' : 'es_MX' }}">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ url('images/logo.png') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @stack('head')

    @include('partials.favicon')
    @include('partials.brand-fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="mera-prog-body">
    <a href="#contenido" class="mera-prog-skip">{{ __('program.skip') }}</a>
    @yield('content')
</body>

</html>
