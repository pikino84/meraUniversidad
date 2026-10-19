@extends('layouts.app')

@section('title', 'Carpetas de curso')

@section('content')
@include('partials.page-header', [
    'title' => 'Carpetas de curso',
    'subtitle' => 'Agrupa los módulos de cada curso por año y versión (México, Latam, EUA…)',
    'action' => ['url' => route('course-groups.create'), 'label' => 'Nueva carpeta'],
])

<div class="mera-results-bar">
    <span>{{ $groups->count() }} {{ $groups->count() === 1 ? 'carpeta' : 'carpetas' }}</span>
    <a href="{{ route('courses.index', ['carpeta' => 'sin']) }}" class="mera-filter-chip text-decoration-none">
        <i class="fas fa-folder-open" aria-hidden="true"></i>
        {{ $ungroupedCount }} {{ $ungroupedCount === 1 ? 'módulo' : 'módulos' }} sin carpeta
    </a>
</div>

@if ($groups->isEmpty())
<div class="card mera-table-card">
    <div class="card-body text-center py-5">
        <i class="fas fa-folder-open" style="font-size:48px;color:#81CFF4" aria-hidden="true"></i>
        <h2 class="h5 mt-3">Aún no hay carpetas de curso</h2>
        <p class="text-muted mb-3">Crea una carpeta por curso y después mueve ahí sus módulos, por año y versión.</p>
        <a href="{{ route('course-groups.create') }}" class="btn mera-btn-primary">Crear la primera carpeta</a>
    </div>
</div>
@else
<ul class="mera-group-list list-unstyled mb-0">
    @foreach ($groups as $group)
    <li class="mera-group-card">
        <a href="{{ route('course-groups.show', $group) }}" class="mera-group-main">
            <i class="fas fa-folder mera-group-icon" aria-hidden="true"></i>
            <span>
                <strong class="mera-group-name">{{ $group->name }}</strong>
                @php($published = $group->versions->where('is_published', true)->count())
                <span class="mera-group-meta">
                    @forelse ($group->versions->groupBy('year') as $year => $yearVersions)
                    <span class="mera-year-pill">
                        <b>{{ $year }}:</b>
                        {{ $yearVersions->map(fn ($v) => $v->market->name.' '.$v->modules_count)->join(' · ') }}
                    </span>
                    @empty
                    <span class="mera-year-pill mera-year-pill-empty">Sin versiones</span>
                    @endforelse
                    @if (\App\Support\ProgramCatalog::isLinked($group))
                    <span class="mera-status mera-status-on"><i class="fas fa-circle" aria-hidden="true"></i> Página del programa</span>
                    @elseif ($published)
                    <span class="mera-status mera-status-on"><i class="fas fa-circle" aria-hidden="true"></i> {{ $published }} {{ $published === 1 ? 'página publicada' : 'páginas publicadas' }}</span>
                    @else
                    <span class="mera-status mera-status-off"><i class="fas fa-circle" aria-hidden="true"></i> Páginas apagadas</span>
                    @endif
                </span>
            </span>
        </a>
        <div class="mera-group-actions">
            <form method="POST" action="{{ route('course-groups.move', $group) }}" class="d-inline">
                @csrf @method('PATCH')
                <input type="hidden" name="direction" value="up">
                <button class="mera-action-btn mera-edit-btn" title="Subir" aria-label="Subir {{ $group->name }}" @disabled($loop->first)>
                    <i class="fas fa-arrow-up" aria-hidden="true"></i>
                </button>
            </form>
            <form method="POST" action="{{ route('course-groups.move', $group) }}" class="d-inline">
                @csrf @method('PATCH')
                <input type="hidden" name="direction" value="down">
                <button class="mera-action-btn mera-edit-btn" title="Bajar" aria-label="Bajar {{ $group->name }}" @disabled($loop->last)>
                    <i class="fas fa-arrow-down" aria-hidden="true"></i>
                </button>
            </form>
            <a href="{{ route('course-groups.edit', $group) }}" class="mera-action-btn mera-edit-btn" title="Editar" aria-label="Editar {{ $group->name }}">
                <i class="fas fa-pencil-alt" aria-hidden="true"></i>
            </a>
            @include('partials.delete-button', [
                'action' => route('course-groups.destroy', $group),
                'label' => "Eliminar carpeta {$group->name}",
                'confirm' => '¿Eliminar la carpeta?',
                'strong' => $group->name,
                'detail' => 'Sus '.$group->versions->sum('modules_count').' módulos NO se borran: regresan a «Sin carpeta» y sus links siguen funcionando. Se borran sus versiones y sus páginas públicas.',
            ])
        </div>
    </li>
    @endforeach
</ul>
@endif
@endsection
