@extends('layouts.app')

@section('title', 'Historial de actividad')

@section('content')
@include('partials.page-header', [
    'title' => 'Historial de actividad',
    'subtitle' => 'Quién cambió qué y cuándo (módulos, carpetas de curso y usuarios)',
])

@php
    $tz = config('mera.display_timezone');
    $events = ['created' => 'Creó', 'updated' => 'Editó', 'deleted' => 'Eliminó', 'restored' => 'Restauró'];
    $active = collect($filters)->filter()->isNotEmpty();
@endphp

<div class="card mera-table-card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('activity.logs.index') }}" class="row g-2 align-items-end" aria-label="Filtrar historial">
            <div class="col-md-6 col-xl-3">
                <label for="log-filter" class="form-label">Módulo del sistema</label>
                <select id="log-filter" name="log" class="form-control form-select mera-input">
                    <option value="">Todos</option>
                    @foreach ($logNames as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['log'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 col-xl-3">
                <label for="log-user" class="form-label">Usuario</label>
                <select id="log-user" name="usuario" class="form-control form-select mera-input">
                    <option value="">Todos</option>
                    @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) ($filters['usuario'] ?? '') === (string) $user->id)>{{ $user->name }} · {{ $user->email }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-xl-2">
                <label for="log-from" class="form-label">Desde</label>
                <input id="log-from" type="date" name="desde" value="{{ $filters['desde'] ?? '' }}" class="form-control mera-input">
            </div>
            <div class="col-6 col-xl-2">
                <label for="log-to" class="form-label">Hasta</label>
                <input id="log-to" type="date" name="hasta" value="{{ $filters['hasta'] ?? '' }}" class="form-control mera-input">
            </div>
            <div class="col-md-12 col-xl-2 d-flex gap-2">
                <button class="btn mera-btn-primary flex-fill"><i class="fa fa-search" aria-hidden="true"></i> Filtrar</button>
                @if ($active)
                <a href="{{ route('activity.logs.index') }}" class="btn mera-btn-cancel" aria-label="Limpiar filtros" title="Limpiar filtros"><i class="fa fa-times" aria-hidden="true"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="mera-results-bar"><span>{{ $logs->total() }} {{ $logs->total() === 1 ? 'registro' : 'registros' }}</span></div>

<div class="card mera-table-card">
    <div class="card-block table-border-style">
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Usuario</th>
                        <th scope="col">Acción</th>
                        <th scope="col">Cambios</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                    @php $info = $describe($log); @endphp
                    <tr>
                        <td data-label="Fecha" class="mera-nowrap">
                            <time datetime="{{ $log->created_at->toIso8601String() }}">
                                {{ $log->created_at->timezone($tz)->format('d/m/Y H:i') }}
                            </time>
                        </td>
                        <td data-label="Usuario">{{ $log->causer?->name ?? 'Sistema' }}</td>
                        <td data-label="Acción">
                            <span class="mera-badge">{{ $events[$log->event] ?? $log->description }}</span>
                            <span class="d-block mt-1">{{ $info['subject'] }}</span>
                        </td>
                        <td data-label="Cambios">
                            @if ($info['changes'])
                            <ul class="mb-0 ps-3 small">
                                @foreach ($info['changes'] as $change)
                                <li>
                                    <strong>{{ $change['field'] }}:</strong>
                                    @if ($change['old'] !== null && $log->event === 'updated')
                                    <del class="text-muted">{{ \Illuminate\Support\Str::limit($change['old'], 60) }}</del> →
                                    @endif
                                    {{ \Illuminate\Support\Str::limit($change['new'] ?? $change['old'] ?? '—', 60) }}
                                </li>
                                @endforeach
                            </ul>
                            @else
                            <span class="text-muted">Sin detalles</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            {{ $active ? 'No hay actividad con esos filtros.' : 'No hay actividad registrada.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
