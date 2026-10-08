@extends('layouts.app')

@section('title', 'Cursos')

@section('content')
@include('partials.page-header', [
    'title' => 'Cursos',
    'subtitle' => 'Administración y gestión de cursos del sistema',
    'action' => ['url' => route('courses.create'), 'label' => 'Nuevo curso'],
])

<div class="card mera-table-card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('courses.index') }}" role="search" aria-label="Filtrar cursos">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <div class="form-group mera-form-group mb-0">
                        <label for="course-search">Buscar curso</label>
                        <input id="course-search" type="search" name="search" class="form-control mera-input"
                            placeholder="Nombre del curso…" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mera-form-group mb-0">
                        <label for="course-filter-category">Categoría</label>
                        @include('categories.components.select', [
                            'name' => 'category_id',
                            'id' => 'course-filter-category',
                            'categories' => $categories,
                            'selected' => request('category_id'),
                            'placeholder' => 'Todas las categorías',
                        ])
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
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
        {{ $courses->total() }} {{ $courses->total() === 1 ? 'curso' : 'cursos' }}
        @if ($filtering) encontrados @endif
    </span>
    @if (request('category_id') && isset($categoryPaths[(int) request('category_id')]))
    <span class="mera-filter-chip">
        <i class="fas fa-tag" aria-hidden="true"></i>
        {{ $categoryPaths[(int) request('category_id')] }} (incluye subcategorías)
    </span>
    @endif
</div>

<div class="card mera-table-card">
    <div class="card-block table-border-style">
        <div class="table-responsive mera-table-desktop">
            <table class="table mera-table table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col">Portada</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Categoría</th>
                        <th scope="col" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($courses as $course)
                    <tr>
                        <td>
                            @include('partials.cover', ['class' => 'img-thumbnail', 'width' => 100, 'height' => 56])
                        </td>
                        <td>
                            <div class="user-name">{{ $course->name }}</div>
                        </td>
                        <td>
                            @include('courses._category-badge')
                        </td>
                        <td class="text-center text-nowrap">
                            @include('courses._actions')
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            @include('courses._empty')
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mera-courses-mobile">
            @forelse ($courses as $course)
            <article class="mera-course-card">
                <div class="mera-course-header">
                    <h2 class="user-name h6 mb-0">{{ $course->name }}</h2>
                </div>
                <div class="mera-course-cover">
                    @include('partials.cover', ['class' => 'img-thumbnail'])
                </div>
                <div class="mera-course-info">
                    <strong>Categoría:</strong>
                    @include('courses._category-badge')
                </div>
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
