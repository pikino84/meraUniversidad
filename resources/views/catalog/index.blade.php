@extends('layouts.public')

@section('title', 'Catálogo de cursos')

@section('content')
<h1 class="h3 mb-1 mera-catalog-title">Catálogo de cursos</h1>
<p class="text-muted mb-4">Explora los cursos disponibles.</p>

<form method="GET" action="{{ route('catalog.index') }}" role="search" class="row g-2 align-items-end mb-4">
    <div class="col-md-6">
        <label for="catalog-search" class="form-label">Buscar</label>
        <input id="catalog-search" type="search" name="search" class="form-control mera-input" value="{{ request('search') }}" placeholder="Nombre del curso…">
    </div>
    <div class="col-md-4">
        <label for="catalog-category" class="form-label">Categoría</label>
        @include('categories.components.select', [
            'name' => 'category_id',
            'id' => 'catalog-category',
            'categories' => $categories,
            'selected' => request('category_id'),
            'placeholder' => 'Todas',
        ])
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button class="btn mera-btn-primary flex-fill">Buscar</button>
        @if (request()->hasAny(['search', 'category_id']))
        <a href="{{ route('catalog.index') }}" class="btn btn-outline-secondary" aria-label="Limpiar filtros">✕</a>
        @endif
    </div>
</form>

<p class="text-muted small" aria-live="polite">{{ $courses->total() }} {{ $courses->total() === 1 ? 'curso' : 'cursos' }}</p>

@if ($courses->isEmpty())
<div class="text-center text-muted py-5">No se encontraron cursos.</div>
@else
<div class="mera-catalog-grid">
    @foreach ($courses as $course)
    <article class="mera-catalog-card">
        @include('partials.cover')
        <div class="mera-catalog-card-body">
            @if ($course->category_id && isset($categoryPaths[$course->category_id]))
            <span class="mera-badge align-self-start">{{ $categoryPaths[$course->category_id] }}</span>
            @endif
            <h2>{{ $course->name }}</h2>
            <p>{{ \Illuminate\Support\Str::limit($course->description, 140) }}</p>
            <a href="{{ $course->content_url }}" target="_blank" rel="noopener" class="btn mera-btn-primary btn-sm align-self-start">
                Abrir curso <span class="visually-hidden">{{ $course->name }} (nueva pestaña)</span>
            </a>
        </div>
    </article>
    @endforeach
</div>

<div class="mt-4">{{ $courses->links() }}</div>
@endif
@endsection
