@extends('layouts.app')

@section('title', $version->label().' · '.$group->name)

@section('content')
@php
    // Nivel versión (Carpeta → Año → Versión): una sección a la vez para no saturar la página
    // (pedido del usuario 2026-10-19). $section: modulos | link | reportes.
    $tabs = [
        'modulos' => ['Módulos', $version->modules_count],
        'link' => ['Link<span class="d-none d-sm-inline"> para compartir</span>', null],
        'reportes' => ['Reportes', $version->reports_count],
    ];
    $programLinks = \App\Support\ProgramCatalog::linkedTo($group);
    $isProgram = $programLinks || \App\Support\ProgramCatalog::isLinked($group);
    $addModulesUrl = route('courses.index', ['carpeta' => 'sin', 'mover_a' => $group->id, 'anio' => $version->year, 'version' => $version->market_id]);
@endphp

<x-folder-header
    :crumbs="[['Carpetas', route('course-groups.index')], [$group->name, route('course-groups.show', $group)], [(string) $version->year, route('course-groups.show', [$group, 'anio' => $version->year])], [$version->market->name, null]]"
    :title="$version->market->name.' · '.$version->year"
    :subtitle="$group->name.' · página en '.($version->language() === 'en' ? 'inglés' : 'español').($version->catalog_code ? ' · '.$version->catalog_code : '')">
</x-folder-header>

<nav class="mera-section-tabs" aria-label="Secciones de {{ $version->label() }}">
    @foreach ($tabs as $key => [$label, $count])
    <a href="{{ route('course-groups.versions.show', [$group, $version, 'seccion' => $key]) }}"
        class="mera-section-tab {{ $section === $key ? 'is-active' : '' }}"
        @if ($section === $key) aria-current="page" @endif>
        <span>{!! $label !!}</span>
        @if ($count !== null)<span class="mera-section-count">{{ $count }}</span>@endif
        @if ($key === 'link')
        <span class="mera-dot {{ $version->is_published || $isProgram ? 'is-on' : '' }}" aria-hidden="true"></span>
        <span class="visually-hidden">({{ $version->is_published || $isProgram ? 'publicada' : 'apagada' }})</span>
        @endif
    </a>
    @endforeach
</nav>

@if ($section === 'modulos')
<section class="card mera-table-card" aria-label="Módulos">
    <div class="card-body">
        <div class="mera-section-toolbar">
            <p class="small text-muted mb-0">
                @if ($modules->count() > 1)
                Arrastra un módulo por su número para cambiar el orden.
                @elseif ($modules->isEmpty())
                Esta versión todavía no tiene módulos.
                @endif
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ $addModulesUrl }}" class="btn btn-sm mera-btn-primary">
                    <i class="fas fa-folder-plus" aria-hidden="true"></i> Agregar módulos
                </a>
                <a href="{{ route('courses.create', ['carpeta' => $group->id, 'anio' => $version->year, 'version' => $version->market_id]) }}" class="btn btn-sm mera-btn-ghost">
                    <i class="fas fa-upload" aria-hidden="true"></i> Subir nuevo
                </a>
            </div>
        </div>

        @if ($modules->isNotEmpty())
        <div class="visually-hidden" aria-live="polite" data-sortable-status></div>
        <ol class="mera-module-list list-unstyled mb-0"
            @if ($modules->count() > 1)
            data-sortable-url="{{ route('course-groups.versions.reorder', [$group, $version]) }}"
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
                    <a href="{{ $module->content_url }}" target="_blank" rel="noopener" class="mera-action-btn mera-edit-btn"
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
                        <button class="mera-action-btn mera-edit-btn" title="Quitar de la carpeta" aria-label="Quitar {{ $module->name }} de la carpeta">
                            <i class="fas fa-unlink" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
            </li>
            @endforeach
        </ol>
        @elseif ($version->reports_count === 0)
        <div class="mera-empty-note">
            <span>¿Esta versión sobra? Como está vacía, se puede eliminar.</span>
            <form method="POST" action="{{ route('course-groups.versions.destroy', [$group, $version]) }}"
                data-confirm="¿Eliminar esta versión?" data-confirm-strong="{{ $group->name }} · {{ $version->label() }}" data-confirm-tone="danger"
                data-confirm-detail="Está vacía: no tiene módulos ni cortes de reporte.">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm mera-btn-ghost mera-btn-ghost-danger"><i class="fas fa-trash-alt" aria-hidden="true"></i> Eliminar versión</button>
            </form>
        </div>
        @endif
    </div>
</section>

@elseif ($section === 'link')
@if ($isProgram)
@include('course-groups._program-page')
@else
@include('course-groups._public-page')
@endif

@else
@include('course-groups._reports')
@endif
@endsection
