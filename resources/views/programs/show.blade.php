@extends('layouts.program')

@section('title', $page['title'])
@section('description', $page['description'] ?? $page['lead'])

@push('head')
    @if (! empty($preview))
    <meta name="robots" content="noindex, nofollow">
    @endif
    @foreach ($alternates as $code => $url)
    <link rel="alternate" hreflang="{{ $code }}" href="{{ $url }}">
    @endforeach
@endpush

@section('content')
@php
    $arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
    $check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
    $total = count($modules);
    $languageNames = ['es' => 'Español', 'en' => 'English'];
@endphp

@if (! empty($preview))
{{-- Solo lo ven administradores: la página está apagada para el público. --}}
<div class="mera-prog-preview" role="status" lang="es">
    <span>👁 <b>Vista previa.</b> Esta página está <b>apagada</b>: el público aún no puede verla.</span>
    <a href="{{ route('course-groups.show', $preview) }}">Ir al panel para publicarla</a>
</div>
@endif

<header class="mera-prog-top">
    <div class="mera-prog-wrap">
        <div class="mera-prog-topbar">
            <img class="mera-prog-logo" src="{{ asset('images/logo-mera-blanco.png') }}" width="480" height="168"
                alt="{{ __('program.logo_alt') }}">

            @if (count($alternates) > 1)
            <nav class="mera-prog-lang" aria-label="{{ __('program.language_switch') }}">
                @foreach ($alternates as $code => $url)
                <a href="{{ $url }}" hreflang="{{ $code }}" lang="{{ $code }}"
                    @if ($code === $language) aria-current="true" @endif>{{ $languageNames[$code] ?? strtoupper($code) }}</a>
                @endforeach
            </nav>
            @endif
        </div>

        @if (! empty($page['eyebrow']))
        <p class="mera-prog-eyebrow">{{ $page['eyebrow'] }}</p>
        @endif
        <h1 class="mera-prog-title">{{ $page['title'] }}</h1>
        <p class="mera-prog-lead">{{ $page['lead'] }}</p>
    </div>
</header>

<main id="contenido" class="mera-prog-wrap mera-prog-main" tabindex="-1"
    data-program-progress="{{ $storageKey }}" data-total="{{ $total }}"
    data-i18n-count="{{ __('program.progress_count', ['done' => '{done}', 'total' => '{total}']) }}"
    data-i18n-seen="{{ __('program.module_seen_suffix') }}">

    {{-- Progreso: se guarda solo en este dispositivo (sin cuentas ni datos personales). --}}
    <section class="mera-prog-progress" aria-labelledby="progreso-titulo" hidden data-progress-box>
        <div class="mera-prog-progress-head">
            <h2 id="progreso-titulo" class="mera-prog-progress-title">{{ __('program.progress_title') }}</h2>
            <span class="mera-prog-progress-count" data-progress-count aria-live="polite">
                {{ __('program.progress_count', ['done' => 0, 'total' => $total]) }}
            </span>
        </div>
        <div class="mera-prog-bar" role="progressbar" aria-labelledby="progreso-titulo"
            aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="0" data-progress-bar>
            <span data-progress-fill></span>
        </div>
    </section>

    <h2 class="mera-prog-sec">{{ __('program.step_modules', ['count' => $total]) }}</h2>
    @if (! empty($emptyPreview))
    <p class="mera-prog-empty" lang="es">Esta versión aún no tiene módulos. Agrégalos en la carpeta del curso, pestaña del idioma.</p>
    @endif
    <ol class="mera-prog-mods">
        @foreach ($modules as $module)
        <li class="mera-prog-mod" data-module="{{ $module['key'] }}">
            @if ($module['url'])
            <a href="{{ $module['url'] }}" target="_blank" rel="noopener noreferrer"
                aria-label="{{ __('program.module_label', ['number' => $module['number'], 'title' => $module['title']]) }}">
                <span class="mera-prog-num" aria-hidden="true">
                    <span class="mera-prog-num-n">{{ $module['number'] }}</span>
                    <span class="mera-prog-num-ok">{!! $check !!}</span>
                </span>
                <span class="mera-prog-tx">
                    <span class="mera-prog-badge mera-prog-badge-next" aria-hidden="true">{{ __('program.badge_next') }}</span>
                    <span class="mera-prog-badge mera-prog-badge-done" aria-hidden="true">{{ __('program.badge_done') }}</span>
                    <h3>{{ $module['title'] }}</h3>
                    <span class="mera-prog-btn">
                        <span class="mera-prog-btn-start">{{ __('program.view_module') }}</span>
                        <span class="mera-prog-btn-again">{{ __('program.review_module') }}</span>
                        {!! $arrow !!}
                    </span>
                </span>
            </a>
            @else
            <div class="mera-prog-mod-off" aria-disabled="true">
                <span class="mera-prog-num" aria-hidden="true"><span class="mera-prog-num-n">{{ $module['number'] }}</span></span>
                <span class="mera-prog-tx">
                    <h3>{{ $module['title'] }}</h3>
                    <span class="mera-prog-soon">{{ __('program.coming_soon') }}</span>
                </span>
            </div>
            @endif
        </li>
        @endforeach
    </ol>

    @if (! empty($actions))
    <h2 class="mera-prog-sec">{{ __('program.step_actions') }}</h2>
    <p class="mera-prog-ready" hidden data-progress-ready role="status">
        {{ __('program.ready', ['count' => $total]) }}
    </p>
    <ul class="mera-prog-acts">
        @foreach ($actions as $action)
        @php $texts = __("program.actions.{$action['type']}"); @endphp
        <li class="mera-prog-act mera-prog-act-{{ $action['type'] }}">
            <a href="{{ $action['url'] }}" target="_blank" rel="noopener noreferrer"
                aria-label="{{ $texts['button'] }} {{ __('program.new_tab') }}">
                <h3>{{ $texts['title'] }}</h3>
                <p>{{ $texts['text'] }}</p>
                <span class="mera-prog-btn">{{ $texts['button'] }} {!! $arrow !!}</span>
            </a>
        </li>
        @endforeach
    </ul>
    @endif

    <p class="mera-prog-note">
        {!! __('program.tip') !!}
        <span hidden data-progress-hint>{{ __('program.tip_progress') }}</span>
    </p>

    <p class="mera-prog-reset" hidden data-progress-reset-wrap>
        <button type="button" data-progress-reset>{{ __('program.reset') }}</button>
    </p>

    <p class="mera-prog-foot"><b>MERA</b> · {{ implode(' · ', __('program.values')) }}</p>
</main>
@endsection
