@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@include('partials.page-header', [
    'title' => 'Dashboard',
    'subtitle' => 'Bienvenido nuevamente, ' . auth()->user()->name . '.',
])

@php
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    $metrics = [
        ['Cursos', $coursesCount, 'fas fa-book', 'mera-green', route('courses.index')],
        ['Usuarios', $usersCount, 'fas fa-users', 'mera-blue', route('users.index')],
        ['Categorías', $categoriesCount, 'fas fa-tags', 'mera-aqua', route('categories.index')],
        ['Roles', $rolesCount, 'fas fa-id-badge', 'mera-dark', $isSuperAdmin ? route('roles.index') : null],
    ];
@endphp

{{-- MÉTRICAS --}}
<div class="row">
    @foreach ($metrics as [$label, $value, $icon, $color, $url])
    <div class="col-xl-3 col-md-6">
        @if ($url)<a href="{{ $url }}" class="text-decoration-none text-reset">@endif
        <div class="mera-dashboard-card">
            <div class="mera-dashboard-icon {{ $color }}"><i class="{{ $icon }}" aria-hidden="true"></i></div>
            <div>
                <span>{{ $label }}</span>
                <h2 class="h3">{{ number_format($value) }}</h2>
            </div>
        </div>
        @if ($url)</a>@endif
    </div>
    @endforeach
</div>

@if ($uncategorizedCount > 0)
<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
    <span>
        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
        Hay <strong>{{ $uncategorizedCount }}</strong> {{ $uncategorizedCount === 1 ? 'curso' : 'cursos' }} sin categoría.
    </span>
    <a href="{{ route('courses.index') }}" class="alert-link">Revisar cursos</a>
</div>
@endif

{{-- ACCESOS RÁPIDOS --}}
<div class="card mera-dashboard-section">
    <div class="card-body">
        <h2 class="mera-section-title h5">Accesos rápidos</h2>

        <div class="row">
            <div class="col-md-4">
                <a href="{{ route('courses.create') }}" class="mera-shortcut">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    <span>Nuevo curso</span>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('categories.create') }}" class="mera-shortcut">
                    <i class="feather icon-plus-circle" aria-hidden="true"></i>
                    <span>Nueva categoría</span>
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
        <h2 class="mera-section-title h5">Cursos recientes</h2>

        @forelse ($recentCourses as $course)
        <div class="mera-recent-item">
            <div>
                <strong>{{ $course->name }}</strong>
                <small>{{ $course->category_id ? ($categoryPaths[$course->category_id] ?? 'Sin categoría') : 'Sin categoría' }}</small>
            </div>
            <a href="{{ route('courses.edit', $course) }}" class="mera-view-btn" aria-label="Editar {{ $course->name }}">Editar</a>
        </div>
        @empty
        <p class="text-muted">
            No hay cursos registrados todavía. <a href="{{ route('courses.create') }}">Crea el primero</a>.
        </p>
        @endforelse
    </div>
</div>
@endsection
