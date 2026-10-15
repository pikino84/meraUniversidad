@extends('layouts.app')

@section('title', $group->name)

@section('content')
@include('partials.page-header', [
    'title' => $group->name,
    'subtitle' => 'Carpeta de curso · módulos por idioma y en orden',
    'action' => ['url' => route('courses.index', ['carpeta' => 'sin', 'mover_a' => $group->id, 'idioma' => $language]), 'label' => 'Agregar módulos'],
])

@php($programLinks = \App\Support\ProgramCatalog::linkedTo($group))
@if ($programLinks || \App\Support\ProgramCatalog::isLinked($group))
@include('course-groups._program-page')
@else
@include('course-groups._public-page')
@endif

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <nav class="mera-lang-tabs" aria-label="Idioma">
        @foreach (\App\Models\CourseGroup::LANGUAGES as $code => $label)
        <a href="{{ route('course-groups.show', [$group, 'idioma' => $code]) }}"
            class="mera-lang-tab {{ $language === $code ? 'is-active' : '' }}"
            @if ($language === $code) aria-current="page" @endif>
            <i class="fas fa-folder{{ $language === $code ? '-open' : '' }}" aria-hidden="true"></i>
            {{ $label }} <span class="mera-lang-count">{{ $counts[$code] }}</span>
        </a>
        @endforeach
    </nav>
    <div class="d-flex gap-2">
        <a href="{{ route('courses.create', ['carpeta' => $group->id, 'idioma' => $language]) }}" class="btn mera-btn-cancel btn-sm">
            <i class="fas fa-upload" aria-hidden="true"></i> Subir módulo nuevo aquí
        </a>
        <a href="{{ route('course-groups.edit', $group) }}" class="btn mera-btn-cancel btn-sm">
            <i class="fas fa-pencil-alt" aria-hidden="true"></i> Editar carpeta
        </a>
    </div>
</div>

<div class="card mera-table-card">
    <div class="card-body">
        @if ($modules->isEmpty())
        <div class="text-center py-4">
            <p class="mb-3 text-muted">
                No hay módulos en {{ \App\Models\CourseGroup::languageLabel($language) }} en esta carpeta.
            </p>
            <a href="{{ route('courses.index', ['carpeta' => 'sin', 'mover_a' => $group->id, 'idioma' => $language]) }}" class="btn mera-btn-primary">
                <i class="fas fa-folder-open" aria-hidden="true"></i> Agregar módulos existentes
            </a>
        </div>
        @else
        @if ($modules->count() > 1)
        <p class="small text-muted mb-2 mera-sortable-hint">
            <i class="fas fa-arrows-alt-v" aria-hidden="true"></i>
            Arrastra un módulo por su número para cambiar el orden, o usa las flechas.
        </p>
        @endif
        <div class="visually-hidden" aria-live="polite" data-sortable-status></div>
        <ol class="mera-module-list list-unstyled mb-0"
            @if ($modules->count() > 1)
            data-sortable-url="{{ route('course-groups.reorder', $group) }}"
            data-sortable-language="{{ $language }}"
            @endif>
            @foreach ($modules as $module)
            <li class="mera-module-row" data-id="{{ $module->id }}">
                <span class="mera-module-num" aria-hidden="true" data-sortable-handle title="Arrastra para cambiar el orden">{{ $loop->iteration }}</span>
                <div class="mera-module-name">
                    <strong>{{ $module->name }}</strong>
                    <small class="text-muted d-block" data-sortable-label>Módulo {{ $loop->iteration }} de {{ $modules->count() }}</small>
                    @php($titleNumber = $module->titleModuleNumber())
                    @if ($titleNumber)
                    {{-- Se muestra solo si no coincide; sortable-modules.js lo actualiza al arrastrar. --}}
                    <small class="mera-missing-badge" data-title-number="{{ $titleNumber }}" title="Revisa el orden: el título indica otro número de módulo." @if ($titleNumber === $loop->iteration) hidden @endif>
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i> El título dice Módulo {{ $titleNumber }}
                    </small>
                    @endif
                </div>
                <div class="mera-module-actions">
                    <form method="POST" action="{{ route('courses.move', $module) }}" class="d-inline" data-sortable-arrow="up">
                        @csrf @method('PATCH')
                        <input type="hidden" name="direction" value="up">
                        <button class="mera-action-btn mera-edit-btn" title="Subir" aria-label="Subir {{ $module->name }}" @disabled($loop->first)>
                            <i class="fas fa-arrow-up" aria-hidden="true"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('courses.move', $module) }}" class="d-inline" data-sortable-arrow="down">
                        @csrf @method('PATCH')
                        <input type="hidden" name="direction" value="down">
                        <button class="mera-action-btn mera-edit-btn" title="Bajar" aria-label="Bajar {{ $module->name }}" @disabled($loop->last)>
                            <i class="fas fa-arrow-down" aria-hidden="true"></i>
                        </button>
                    </form>
                    @if ($module->content_url)
                    <a href="{{ $module->content_url }}" target="_blank" rel="noopener" class="mera-action-btn mera-add-btn"
                        title="Ver módulo" aria-label="Ver {{ $module->name }} (nueva pestaña)">
                        <i class="fa fa-eye" aria-hidden="true"></i>
                    </a>
                    @endif
                    <a href="{{ route('courses.edit', $module) }}" class="mera-action-btn mera-edit-btn"
                        title="Editar" aria-label="Editar {{ $module->name }}">
                        <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                    </a>
                    <form method="POST" action="{{ route('courses.ungroup', $module) }}" class="d-inline"
                        data-confirm="¿Quitar el módulo de esta carpeta?" data-confirm-strong="{{ $module->name }}" data-confirm-tone="info"
                        data-confirm-detail="El módulo no se borra: regresa a «Sin carpeta» y su link sigue igual."
                        data-confirm-button="Quitar">
                        @csrf @method('DELETE')
                        <button class="mera-action-btn mera-neutral-btn" title="Quitar de la carpeta" aria-label="Quitar {{ $module->name }} de la carpeta">
                            <i class="fas fa-unlink" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </li>
            @endforeach
        </ol>
        @endif
    </div>
</div>

@if ($withoutLanguage->isNotEmpty())
<p class="text-muted small mt-3">
    {{ $withoutLanguage->count() }} módulo(s) de esta carpeta no tienen idioma. Edítalos para asignarles Español o Inglés.
</p>
@endif

@include('course-groups._reports')

<p class="mt-3"><a href="{{ route('course-groups.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Todas las carpetas</a></p>
@endsection
