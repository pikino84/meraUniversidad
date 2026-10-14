@extends('layouts.app')

@section('title', 'Papelera')

@section('content')
@include('partials.page-header', [
    'title' => 'Papelera',
    'subtitle' => "Módulos eliminados en los últimos {$days} días. Al restaurarlos, su link vuelve a funcionar.",
])

@php $tz = config('mera.display_timezone'); @endphp

<div class="mera-results-bar"><span>{{ $courses->total() }} {{ $courses->total() === 1 ? 'módulo' : 'módulos' }} en la papelera</span></div>

<div class="card mera-table-card">
    <div class="card-block table-border-style">
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col">Módulo</th>
                        <th scope="col">Carpeta</th>
                        <th scope="col">Eliminado</th>
                        <th scope="col">Se purga</th>
                        <th scope="col" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($courses as $course)
                    @php
                        $purgeAt = $course->deleted_at->copy()->addDays($days);
                        $canRestore = $restorable[$course->id] ?? false;
                    @endphp
                    <tr>
                        <td data-label="Módulo">
                            <div class="user-name">{{ $course->name }}</div>
                            <code class="small text-muted">{{ $course->path }}</code>
                        </td>
                        <td data-label="Carpeta">{{ $course->group?->name ?? 'Sin carpeta' }}</td>
                        <td data-label="Eliminado" class="mera-nowrap">
                            <time datetime="{{ $course->deleted_at->toIso8601String() }}">{{ $course->deleted_at->timezone($tz)->format('d/m/Y H:i') }}</time>
                        </td>
                        <td data-label="Se purga" class="mera-nowrap">
                            @if ($canRestore)
                            {{ $purgeAt->timezone($tz)->format('d/m/Y') }}
                            <small class="text-muted d-block">{{ $purgeAt->isPast() ? 'en la próxima purga' : $purgeAt->diffForHumans() }}</small>
                            @else
                            <span class="mera-missing-badge"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Archivos ya purgados</span>
                            @endif
                        </td>
                        <td data-label="Acciones" class="text-center text-nowrap">
                            @if ($canRestore)
                            <form method="POST" action="{{ route('trash.restore', $course) }}" class="d-inline"
                                data-confirm="¿Restaurar el módulo?" data-confirm-strong="{{ $course->name }}" data-confirm-tone="info"
                                data-confirm-detail="Vuelve a la lista de módulos y su link funciona de nuevo. Si estaba en una carpeta, regresa al final de su idioma."
                                data-confirm-button="Restaurar">
                                @csrf
                                <button type="submit" class="btn btn-sm mera-btn-primary" aria-label="Restaurar {{ $course->name }}">
                                    <i class="fas fa-undo" aria-hidden="true"></i> Restaurar
                                </button>
                            </form>
                            @else
                            <span class="text-muted small">No se puede restaurar</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="mera-empty-state">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                            <strong>La papelera está vacía</strong>
                            <span>Cuando se elimine un módulo aparecerá aquí durante {{ $days }} días, por si hay que restaurarlo.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $courses->links() }}</div>
    </div>
</div>

<p class="small text-muted mt-2">
    ¿Buscas un módulo que se borró antes del 14 de octubre de 2026? No aparece aquí: pide ayuda a soporte para recuperarlo.
</p>
@endsection
