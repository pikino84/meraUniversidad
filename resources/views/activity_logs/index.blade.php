@extends('layouts.app')

@section('title', 'Historial de Actividad')

@section('content')
<div class="page-header">
    <div class="row">
        <div class="col-md-12">
            <h5>Historial de Actividad</h5>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-block table-border-style">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Modelo</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>{{ optional($log->causer)->name ?? 'Sistema' }}</td>
                            <td>{{ $log->description }}</td>
                            <td>{{ class_basename($log->subject_type) }}</td>
                            <td>
                                @if ($log->properties && $log->properties->has('attributes'))
                                    <ul>
                                        @foreach ($log->properties['attributes'] as $key => $value)
                                            <li><strong>{{ $key }}:</strong> {{ $value }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-muted">Sin detalles</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No hay actividad registrada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
