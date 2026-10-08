@extends('layouts.app')

@section('title', 'Historial de actividad')

@section('content')
@include('partials.page-header', [
    'title' => 'Historial de actividad',
    'subtitle' => 'Quién cambió qué y cuándo (cursos, categorías y usuarios)',
])

@php
    $tz = config('mera.display_timezone');
    $events = ['created' => 'Creó', 'updated' => 'Editó', 'deleted' => 'Eliminó'];
@endphp

<div class="card mera-table-card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('activity.logs.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="log-filter" class="form-label">Módulo</label>
                <select id="log-filter" name="log" class="form-control form-select mera-input" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach ($logNames as $name)
                    <option value="{{ $name }}" @selected(request('log') === $name)>{{ ucfirst($name) }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card mera-table-card">
    <div class="card-block table-border-style">
        <div class="table-responsive">
            <table class="table mera-table table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Usuario</th>
                        <th scope="col">Acción</th>
                        <th scope="col">Registro</th>
                        <th scope="col">Cambios</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap">
                            <time datetime="{{ $log->created_at->toIso8601String() }}">
                                {{ $log->created_at->timezone($tz)->format('d/m/Y H:i') }}
                            </time>
                        </td>
                        <td>{{ $log->causer?->name ?? 'Sistema' }}</td>
                        <td>
                            <span class="mera-badge">{{ $events[$log->event] ?? $log->description }}</span>
                            <small class="text-muted d-block">{{ $log->log_name }}</small>
                        </td>
                        <td>{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</td>
                        <td>
                            @php
                                $new = $log->properties['attributes'] ?? [];
                                $old = $log->properties['old'] ?? [];
                            @endphp
                            @if ($new || $old)
                            <ul class="mb-0 ps-3 small">
                                @foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key)
                                <li>
                                    <strong>{{ $key }}:</strong>
                                    @if (array_key_exists($key, $old))
                                    <del class="text-muted">{{ \Illuminate\Support\Str::limit(is_scalar($old[$key]) ? (string) $old[$key] : json_encode($old[$key]), 60) }}</del>
                                    →
                                    @endif
                                    {{ \Illuminate\Support\Str::limit(is_scalar($new[$key] ?? null) ? (string) ($new[$key] ?? '') : json_encode($new[$key] ?? null), 60) }}
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
                        <td colspan="5" class="text-center py-4">No hay actividad registrada.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
