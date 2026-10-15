@extends('layouts.app')

@section('title', 'Módulos')

@section('content')
@include('partials.page-header', [
    'title' => 'Módulos',
    'subtitle' => 'Cada módulo es un paquete ZIP; agrúpalos en carpetas de curso',
    'action' => ['url' => route('courses.create'), 'label' => 'Nuevo módulo'],
])

@php($activeFilters = collect([request('search'), request('carpeta'), request('category_id'), request('orden')])->filter(fn ($v) => filled($v))->count())
{{-- U19: en móvil los filtros se pliegan para que el primer resultado se vea sin hacer scroll. --}}
<button class="btn mera-btn-cancel w-100 mb-2 d-md-none mera-filters-toggle" type="button" data-filters-toggle
    aria-expanded="false" aria-controls="course-filters">
    <i class="fa fa-filter" aria-hidden="true"></i> Filtros @if ($activeFilters) ({{ $activeFilters }}) @endif
</button>
<div class="card mera-table-card mb-3 mera-filters" id="course-filters">
    <div class="card-body">
        <form method="GET" action="{{ route('courses.index') }}" role="search" aria-label="Filtrar módulos">
            {{-- Auditoría UI 2026-10-14 (#5): grid con ancho mínimo por filtro (los selects ya no se truncan). --}}
            <div class="mera-filter-grid {{ config('mera.categories_enabled') ? '' : 'mera-filter-grid--no-category' }}">
                <div class="col-md-6 col-xl-3">
                    <div class="form-group mera-form-group mb-0">
                        <label for="course-search">Buscar módulo</label>
                        <input id="course-search" type="search" name="search" class="form-control mera-input"
                            placeholder="Nombre del módulo…" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-6 col-xl-2">
                    <div class="form-group mera-form-group mb-0">
                        <label for="course-filter-group">Carpeta</label>
                        <select id="course-filter-group" name="carpeta" class="form-control form-select mera-input">
                            <option value="">Todas</option>
                            <option value="sin" @selected(request('carpeta') === 'sin')>Sin carpeta</option>
                            @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((string) request('carpeta') === (string) $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if (config('mera.categories_enabled'))
                <div class="col-md-6 col-xl-2">
                    <div class="form-group mera-form-group mb-0">
                        <label for="course-filter-category">Categoría</label>
                        @include('categories.components.select', [
                            'name' => 'category_id',
                            'id' => 'course-filter-category',
                            'categories' => $categories,
                            'selected' => request('category_id'),
                            'placeholder' => 'Todas',
                        ])
                    </div>
                </div>
                @endif
                <div class="col-md-6 col-xl-2">
                    <div class="form-group mera-form-group mb-0">
                        <label for="course-sort">Ordenar por</label>
                        <select id="course-sort" name="orden" class="form-control form-select mera-input">
                            <option value="">Más recientes</option>
                            <option value="actualizados" @selected(request('orden') === 'actualizados')>Últimos actualizados</option>
                            <option value="nombre" @selected(request('orden') === 'nombre')>Nombre (A-Z)</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3 d-flex gap-2">
                    <button class="btn mera-btn-primary flex-fill">
                        <i class="fa fa-search" aria-hidden="true"></i> Buscar
                    </button>
                    @if ($filtering)
                    <a href="{{ route('courses.index') }}" class="btn mera-btn-cancel" title="Limpiar filtros" aria-label="Limpiar filtros">
                        <i class="fa fa-times" aria-hidden="true"></i>
                    </a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<div class="mera-results-bar" aria-live="polite">
    <span>
        {{ $courses->total() }} {{ $courses->total() === 1 ? 'módulo' : 'módulos' }}
        @if ($filtering) encontrados @endif
    </span>
    @if (request('carpeta') === 'sin')
    <span class="mera-filter-chip"><i class="fas fa-folder-open" aria-hidden="true"></i> Sin carpeta</span>
    @elseif (ctype_digit((string) request('carpeta')) && ($chip = $groups->firstWhere('id', (int) request('carpeta'))))
    <span class="mera-filter-chip"><i class="fas fa-folder" aria-hidden="true"></i> {{ $chip->name }}</span>
    @endif
    @if (config('mera.categories_enabled') && request('category_id') && isset($categoryPaths[(int) request('category_id')]))
    <span class="mera-filter-chip">
        <i class="fas fa-tag" aria-hidden="true"></i>
        {{ $categoryPaths[(int) request('category_id')] }} (incluye subcategorías)
    </span>
    @endif
</div>

{{-- Selección múltiple → "Mover a…" (CLAUDE.md §10). Se muestra al marcar módulos. --}}
<form method="POST" action="{{ route('courses.bulk-move') }}" data-bulk-move>
    @csrf
    <div class="mera-bulk-bar" data-bulk-bar hidden role="region" aria-label="Mover módulos seleccionados">
        <strong data-bulk-count aria-live="polite">0 módulos seleccionados</strong>
        <label class="visually-hidden" for="bulk-group">Mover a la carpeta</label>
        <select id="bulk-group" name="course_group_id" class="form-control form-select mera-input" data-bulk-group>
            <option value="">— Sin carpeta —</option>
            @foreach ($groups as $group)
            <option value="{{ $group->id }}" @selected((string) request('mover_a') === (string) $group->id)>{{ $group->name }}</option>
            @endforeach
            <option value="{{ \App\Http\Controllers\CourseGroupController::NEW_GROUP }}" @selected(old('course_group_id') === \App\Http\Controllers\CourseGroupController::NEW_GROUP)>+ Nueva carpeta…</option>
        </select>
        <label class="visually-hidden" for="bulk-new-group">Nombre de la carpeta nueva</label>
        <input id="bulk-new-group" type="text" name="new_group_name" maxlength="150" value="{{ old('new_group_name') }}"
            class="form-control mera-input mera-bulk-new" placeholder="Nombre de la carpeta nueva" data-bulk-new hidden>
        <label class="visually-hidden" for="bulk-language">Idioma</label>
        <select id="bulk-language" name="language" class="form-control form-select mera-input" data-bulk-language>
            @foreach (\App\Models\CourseGroup::LANGUAGES as $code => $label)
            <option value="{{ $code }}" @selected(request('idioma', 'es') === $code)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn mera-btn-primary"><i class="fas fa-folder-open" aria-hidden="true"></i> Mover</button>
        <button type="button" class="btn mera-btn-cancel" data-bulk-clear>Cancelar</button>
    </div>
</form>

<div class="card mera-table-card">
    <div class="card-block table-border-style">
        <div class="table-responsive mera-table-desktop">
            <table class="table mera-table table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col" class="mera-col-check">
                            <input type="checkbox" class="form-check-input" data-bulk-all aria-label="Seleccionar todos los de esta página">
                        </th>
                        <th scope="col" class="d-none d-xxl-table-cell">Portada</th>
                        <th scope="col" class="mera-col-name">Nombre</th>
                        <th scope="col" class="mera-nowrap">Carpeta · Idioma</th>
                        @if (config('mera.categories_enabled'))
                        <th scope="col" class="d-none d-xl-table-cell">Categoría</th>
                        @endif
                        <th scope="col" class="d-none d-lg-table-cell">Actualizado</th>
                        <th scope="col" class="text-center mera-col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($courses as $course)
                    <tr>
                        <td class="mera-col-check">
                            <input type="checkbox" class="form-check-input" value="{{ $course->id }}" data-bulk-item
                                aria-label="Seleccionar {{ $course->name }}">
                        </td>
                        <td class="d-none d-xxl-table-cell">
                            @include('partials.cover', ['class' => 'img-thumbnail', 'width' => 96, 'height' => 54])
                        </td>
                        <td class="mera-col-name">
                            <div class="user-name">{{ $course->name }}</div>
                            @include('courses._missing-badge')
                        </td>
                        <td>@include('courses._group-badge')</td>
                        @if (config('mera.categories_enabled'))
                        <td class="d-none d-xl-table-cell mera-nowrap">@include('courses._category-badge')</td>
                        @endif
                        <td class="d-none d-lg-table-cell mera-nowrap">
                            <time datetime="{{ $course->updated_at?->toIso8601String() }}" title="{{ $course->updated_at?->timezone(config('mera.display_timezone'))->format('d/m/Y H:i') }}">
                                {{ $course->updated_at?->timezone(config('mera.display_timezone'))->format('d/m/Y') }}
                            </time>
                        </td>
                        <td class="text-center text-nowrap mera-col-actions">
                            @include('courses._actions')
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            @include('courses._empty')
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mera-courses-mobile">
            @if ($courses->isNotEmpty())
            <label class="mera-bulk-all-mobile">
                <input type="checkbox" class="form-check-input" data-bulk-all> Seleccionar todos
            </label>
            @endif
            @forelse ($courses as $course)
            <article class="mera-course-card">
                <div class="mera-course-header d-flex gap-2 align-items-start">
                    <input type="checkbox" class="form-check-input mt-1" value="{{ $course->id }}" data-bulk-item
                        aria-label="Seleccionar {{ $course->name }}">
                    <div>
                        <h2 class="user-name h6 mb-0">{{ $course->name }}</h2>
                        @include('courses._missing-badge')
                    </div>
                </div>
                <div class="mera-course-cover">
                    @include('partials.cover', ['class' => 'img-thumbnail'])
                </div>
                <div class="mera-course-info">
                    <strong>Carpeta:</strong> @include('courses._group-badge')
                </div>
                @if (config('mera.categories_enabled'))
                <div class="mera-course-info">
                    <strong>Categoría:</strong>
                    @include('courses._category-badge')
                </div>
                @endif
                <div class="mera-course-actions">
                    @include('courses._actions')
                </div>
            </article>
            @empty
            <div class="text-center py-4">@include('courses._empty')</div>
            @endforelse
        </div>

        <div class="mt-3">
            {{ $courses->links() }}
        </div>
    </div>
</div>
@endsection
