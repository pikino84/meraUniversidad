@extends('layouts.app')

@section('title', 'Catálogo de cursos')

@section('content')
@include('partials.page-header', [
    'title' => 'Catálogo de cursos',
    'subtitle' => 'Cursos de Capacitación por código. Es la base del seguimiento anual por persona.',
])

<details class="card mera-table-card mb-3 mera-catalog-upload" @if ($total === 0) open @endif>
    <summary class="card-body d-flex align-items-center gap-2">
        <i class="fas fa-file-excel" aria-hidden="true"></i>
        <strong>Cargar o actualizar desde el Excel del catálogo</strong>
    </summary>
    <div class="card-body pt-0">
        <p class="small text-muted">
            Sube el <strong>Catalogo_MERA_v*.xlsx</strong> de Capacitación. Se agregan los cursos nuevos y se actualizan
            nombre, horas, instructor, etc. No se borra ningún curso ni se cambia lo que se ajusta aquí
            (calificación mínima, evaluación, si cuenta y códigos de la plataforma).
        </p>
        <form method="POST" action="{{ route('course-catalog.import') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
            @csrf
            <label class="visually-hidden" for="catalog-file">Excel del catálogo</label>
            <input id="catalog-file" type="file" name="catalog" accept=".xlsx" required class="form-control mera-input" style="max-width:420px">
            <button type="submit" class="btn btn-sm mera-btn-save"><i class="fas fa-upload" aria-hidden="true"></i> Cargar catálogo</button>
        </form>
    </div>
</details>

<form method="GET" action="{{ route('course-catalog.index') }}" class="mera-catalog-filters mb-3">
    <label class="visually-hidden" for="f-anio">Año</label>
    <select id="f-anio" name="anio" class="form-select mera-input">
        <option value="">Todos los años</option>
        @foreach ($years as $y)
        <option value="{{ $y }}" @selected($filters['anio'] === $y)>{{ $y }}</option>
        @endforeach
    </select>
    <label class="visually-hidden" for="f-ruta">Ruta</label>
    <select id="f-ruta" name="ruta" class="form-select mera-input">
        <option value="">Todas las rutas</option>
        @foreach (array_keys(\App\Models\CatalogCourse::ROUTES) as $route)
        <option value="{{ $route }}" @selected($filters['ruta'] === $route)>{{ $route }}</option>
        @endforeach
    </select>
    <label class="visually-hidden" for="f-q">Buscar</label>
    <input id="f-q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control mera-input" placeholder="Código o nombre">
    <button type="submit" class="btn mera-btn-primary">Buscar</button>
</form>

<div class="mera-results-bar">
    <span>{{ $courses->total() }} {{ $courses->total() === 1 ? 'curso' : 'cursos' }}@if ($courses->total() !== $total) de {{ $total }}@endif</span>
    @if (array_filter($filters))
    <a href="{{ route('course-catalog.index') }}" class="mera-filter-chip text-decoration-none"><i class="fas fa-times" aria-hidden="true"></i> Quitar filtros</a>
    @endif
</div>

<div class="card mera-table-card">
    <div class="card-body">
        @if ($courses->isEmpty())
        <div class="mera-empty-state">
            <i class="fas fa-list-alt" aria-hidden="true"></i>
            <strong>{{ $total === 0 ? 'El catálogo está vacío' : 'Sin resultados' }}</strong>
            <span>{{ $total === 0 ? 'Sube el Excel del catálogo para empezar.' : 'Prueba con otro filtro.' }}</span>
        </div>
        @else
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack table-hover mb-0">
                <thead>
                    <tr>
                        <th scope="col">Curso</th>
                        <th scope="col">Año · País</th>
                        <th scope="col">Horas</th>
                        <th scope="col">Para aprobar</th>
                        <th scope="col">Seguimiento</th>
                        <th scope="col" class="text-center mera-col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($courses as $course)
                    <tr>
                        <td data-label="Curso">
                            <span class="user-name">{{ $course->short_title }}</span>
                            <code class="mera-code-badge d-inline-block mt-1">{{ $course->code }}</code>
                            @if ($course->aliases)
                            <small class="d-block text-muted mt-1">En la plataforma: {{ implode(', ', $course->aliases) }}</small>
                            @endif
                        </td>
                        <td data-label="Año · País" class="mera-nowrap">{{ $course->year }} · {{ $course->countryLabel() }}<small class="d-block text-muted">{{ $course->route }} · {{ $course->modality }}</small></td>
                        <td data-label="Horas">{{ $course->hours !== null ? rtrim(rtrim(number_format($course->hours, 2), '0'), '.') : '—' }}</td>
                        <td data-label="Para aprobar" class="mera-nowrap">
                            @if ($course->has_evaluation)
                            100 % y {{ $course->passing_grade }} o más
                            @else
                            100 % <small class="d-block text-muted">sin evaluación</small>
                            @endif
                        </td>
                        <td data-label="Seguimiento">
                            @if ($course->in_tracking)
                            <span class="mera-status mera-status-on"><i class="fas fa-circle" aria-hidden="true"></i> Cuenta</span>
                            @else
                            <span class="mera-status mera-status-off"><i class="fas fa-circle" aria-hidden="true"></i> No cuenta</span>
                            @endif
                        </td>
                        <td data-label="Acciones" class="text-center mera-col-actions">
                            <a href="{{ route('course-catalog.edit', $course) }}" class="mera-action-btn mera-edit-btn" title="Ajustar" aria-label="Ajustar {{ $course->short_title }}">
                                <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@if ($courses->hasPages())
<div class="mt-3">{{ $courses->links() }}</div>
@endif
@endsection
