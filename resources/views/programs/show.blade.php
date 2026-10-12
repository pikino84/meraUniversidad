@extends('layouts.program')

@section('title', $program['title'])
@section('description', $program['description'] ?? $program['lead'])

@section('content')
@php
    $arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
    $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
    $total = count($modules);
@endphp

<header class="mera-prog-top">
    <div class="mera-prog-wrap">
        <img class="mera-prog-logo" src="{{ asset('images/logo-mera-blanco.png') }}" width="480" height="168"
            alt="MERA · Vision, Values, Results">
        @if (! empty($program['eyebrow']))
        <p class="mera-prog-eyebrow">{{ $program['eyebrow'] }}</p>
        @endif
        <h1 class="mera-prog-title">{{ $program['title'] }}</h1>
        <p class="mera-prog-lead">{{ $program['lead'] }}</p>
    </div>
</header>

<main id="contenido" class="mera-prog-wrap mera-prog-main" tabindex="-1"
    data-program-progress="{{ $slug }}" data-total="{{ $total }}">

    {{-- Progreso: se guarda solo en este dispositivo (sin cuentas ni datos personales). --}}
    <section class="mera-prog-progress" aria-labelledby="progreso-titulo" hidden data-progress-box>
        <div class="mera-prog-progress-head">
            <h2 id="progreso-titulo" class="mera-prog-progress-title">Tu avance</h2>
            <span class="mera-prog-progress-count" data-progress-count aria-live="polite">0 de {{ $total }} módulos</span>
        </div>
        <div class="mera-prog-bar" role="progressbar" aria-labelledby="progreso-titulo"
            aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="0" data-progress-bar>
            <span data-progress-fill></span>
        </div>
    </section>

    <h2 class="mera-prog-sec">Paso 1 · Tus {{ $total }} módulos</h2>
    <ol class="mera-prog-mods">
        @foreach ($modules as $module)
        <li class="mera-prog-mod" data-module="{{ $module['key'] }}">
            @if ($module['url'])
            <a href="{{ $module['url'] }}" target="_blank" rel="noopener noreferrer"
                aria-label="Módulo {{ $module['number'] }}: {{ $module['title'] }} (se abre en una pestaña nueva)">
                <span class="mera-prog-num" aria-hidden="true">
                    <span class="mera-prog-num-n">{{ $module['number'] }}</span>
                    <span class="mera-prog-num-ok">{!! $check !!}</span>
                </span>
                <span class="mera-prog-tx">
                    <span class="mera-prog-badge mera-prog-badge-next" aria-hidden="true">Continúa aquí</span>
                    <span class="mera-prog-badge mera-prog-badge-done" aria-hidden="true">Visto</span>
                    <h3>{{ $module['title'] }}</h3>
                    <span class="mera-prog-btn">
                        <span class="mera-prog-btn-start">Ver módulo</span>
                        <span class="mera-prog-btn-again">Repasar</span>
                        {!! $arrow !!}
                    </span>
                </span>
            </a>
            @else
            <div class="mera-prog-mod-off" aria-disabled="true">
                <span class="mera-prog-num" aria-hidden="true"><span class="mera-prog-num-n">{{ $module['number'] }}</span></span>
                <span class="mera-prog-tx">
                    <h3>{{ $module['title'] }}</h3>
                    <span class="mera-prog-soon">Disponible próximamente</span>
                </span>
            </div>
            @endif
        </li>
        @endforeach
    </ol>

    @if (! empty($program['actions']))
    <h2 class="mera-prog-sec">Paso 2 · Al terminar los módulos</h2>
    <p class="mera-prog-ready" hidden data-progress-ready role="status">
        ¡Terminaste los {{ $total }} módulos! Ya puedes hacer la evaluación.
    </p>
    <ul class="mera-prog-acts">
        @foreach ($program['actions'] as $action)
        <li class="mera-prog-act mera-prog-act-{{ $action['type'] ?? 'default' }}">
            <a href="{{ $action['url'] }}" target="_blank" rel="noopener noreferrer"
                aria-label="{{ $action['button'] }} (se abre en una pestaña nueva)">
                <h3>{{ $action['title'] }}</h3>
                <p>{{ $action['text'] }}</p>
                <span class="mera-prog-btn">{{ $action['button'] }} {!! $arrow !!}</span>
            </a>
        </li>
        @endforeach
    </ul>
    @endif

    <p class="mera-prog-note">
        <b>Consejo:</b> los módulos se abren en una pestaña nueva. Cuando termines uno, regresa a esta página para entrar al siguiente.
        <span hidden data-progress-hint>Marcamos con ✓ los módulos que ya abriste en este dispositivo.</span>
    </p>

    <p class="mera-prog-reset" hidden data-progress-reset-wrap>
        <button type="button" data-progress-reset>Reiniciar mi avance</button>
    </p>

    @if (! empty($program['values']))
    <p class="mera-prog-foot"><b>MERA</b> · {{ implode(' · ', $program['values']) }}</p>
    @endif
</main>
@endsection
