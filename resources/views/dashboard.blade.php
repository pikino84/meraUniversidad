@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@include('partials.page-header', [
    'title' => 'Dashboard',
    'subtitle' => 'Bienvenido nuevamente, ' . auth()->user()->name . '.',
])

@php
    $metrics = [
        ['Carpetas de curso', $groupsCount, 'fas fa-folder-open', 'mera-green', route('course-groups.index'),
            $publishedGroupsCount . ' ' . ($publishedGroupsCount === 1 ? 'publicada' : 'publicadas')],
        ['Módulos', $coursesCount, 'fas fa-book', 'mera-aqua', route('courses.index'), null],
        ['Usuarios', $usersCount, 'fas fa-users', 'mera-blue', route('users.index'), null],
        ['Categorías', $categoriesCount, 'fas fa-tags', 'mera-dark', route('categories.index'), null],
    ];
@endphp

{{-- MÉTRICAS --}}
<div class="row">
    @foreach ($metrics as [$label, $value, $icon, $color, $url, $note])
    <div class="col-xl-3 col-md-6">
        <a href="{{ $url }}" class="text-decoration-none text-reset">
            <div class="mera-dashboard-card">
                <div class="mera-dashboard-icon {{ $color }}"><i class="{{ $icon }}" aria-hidden="true"></i></div>
                <div>
                    <span>{{ $label }}</span>
                    <h2 class="h3">{{ number_format($value) }}</h2>
                    @if ($note)<small class="text-muted">{{ $note }}</small>@endif
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>

@if ($ungroupedCount > 0)
<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
    <span>
        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
        Hay <strong>{{ $ungroupedCount }}</strong> {{ $ungroupedCount === 1 ? 'módulo' : 'módulos' }} sin carpeta de curso.
    </span>
    <a href="{{ route('courses.index', ['carpeta' => 'sin']) }}" class="alert-link">Organizar módulos</a>
</div>
@endif

{{-- ACCESOS RÁPIDOS --}}
<div class="card mera-dashboard-section">
    <div class="card-body">
        <h2 class="mera-section-title h5">Accesos rápidos</h2>

        <div class="row">
            <div class="col-md-4">
                <a href="{{ route('course-groups.create') }}" class="mera-shortcut">
                    <i class="fas fa-folder-open" aria-hidden="true"></i>
                    <span>Nueva carpeta</span>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('courses.create') }}" class="mera-shortcut">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    <span>Nuevo módulo</span>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('users.create') }}" class="mera-shortcut">
                    <i class="fas fa-user-plus" aria-hidden="true"></i>
                    <span>Nuevo usuario</span>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- CURSOS RECIENTES --}}
<div class="card mera-dashboard-section">
    <div class="card-body">
        <h2 class="mera-section-title h5">Módulos recientes</h2>

        @forelse ($recentCourses as $course)
        <div class="mera-recent-item">
            <div>
                <strong>{{ $course->name }}</strong>
                <small>
                    @if ($course->group)
                    <i class="fas fa-folder-open" aria-hidden="true"></i> {{ $course->group->name }}
                    @else
                    Sin carpeta
                    @endif
                    @if ($course->category_id && isset($categoryPaths[$course->category_id]))
                    · {{ $categoryPaths[$course->category_id] }}
                    @endif
                </small>
            </div>
            <a href="{{ route('courses.edit', $course) }}" class="mera-view-btn" aria-label="Editar {{ $course->name }}">Editar</a>
        </div>
        @empty
        <p class="text-muted">
            No hay módulos registrados todavía. <a href="{{ route('courses.create') }}">Sube el primero</a>.
        </p>
        @endforelse
    </div>
</div>

@if ($uncategorizedCount > 0)
<p class="small text-muted mt-2">
    <i class="fas fa-tags" aria-hidden="true"></i>
    {{ $uncategorizedCount }} {{ $uncategorizedCount === 1 ? 'módulo no tiene' : 'módulos no tienen' }} categoría (las categorías están en pausa mientras se usan las carpetas).
</p>
@endif
@endsection
