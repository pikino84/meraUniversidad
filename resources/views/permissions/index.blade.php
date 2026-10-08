@extends('layouts.app')

@section('title', 'Permisos')

@section('content')
@include('partials.page-header', [
    'title' => 'Permisos',
    'subtitle' => 'Administración y gestión de permisos del sistema',
    'action' => ['url' => route('permissions.create'), 'label' => 'Nuevo permiso'],
])

<div class="card mera-table-card">
    <div class="card-block table-border-style">
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Roles que lo usan</th>
                        <th scope="col" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($permissions as $permission)
                    <tr>
                        <td data-label="Permiso"><div class="user-name">{{ $permission->name }}</div></td>
                        <td data-label="Roles que lo usan">{{ $permission->roles_count }}</td>
                        <td class="text-center text-nowrap">
                            <a href="{{ route('permissions.edit', $permission) }}" class="mera-action-btn mera-edit-btn"
                                title="Editar" aria-label="Editar permiso {{ $permission->name }}">
                                <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                            </a>
                            @include('partials.delete-button', [
                                'action' => route('permissions.destroy', $permission),
                                'label' => "Eliminar permiso {$permission->name}",
                                'confirm' => "¿Eliminar el permiso «{$permission->name}»?",
                                'detail' => $permission->roles_count
                                    ? "Se quitará de {$permission->roles_count} rol(es). Esta acción no se puede deshacer."
                                    : 'Esta acción no se puede deshacer.',
                            ])
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center py-4">No hay permisos registrados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $permissions->links() }}</div>
    </div>
</div>
@endsection
