@extends('layouts.app')

@section('title', 'Roles')

@section('content')
@include('partials.page-header', [
    'title' => 'Roles',
    'subtitle' => 'Administración de roles y permisos del sistema',
    'action' => ['url' => route('roles.create'), 'label' => 'Nuevo rol'],
])

<div class="card mera-table-card">
    <div class="card-block table-border-style">
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col">Rol</th>
                        <th scope="col">Usuarios</th>
                        <th scope="col">Permisos</th>
                        <th scope="col" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                    @php($isSystem = in_array($role->name, $systemRoles, true))
                    <tr>
                        <td data-label="Rol">
                            <div class="user-name">
                                {{ $role->name }}
                                @if ($isSystem)
                                <small class="text-muted" title="Rol del sistema: no se puede renombrar ni eliminar">
                                    <i class="fa fa-lock" aria-hidden="true"></i> sistema
                                </small>
                                @endif
                            </div>
                        </td>
                        <td data-label="Usuarios">{{ $role->users_count }}</td>
                        <td data-label="Permisos">
                            @forelse ($role->permissions as $permission)
                            <span class="mera-badge">{{ $permission->name }}</span>
                            @empty
                            <span class="text-muted">Sin permisos</span>
                            @endforelse
                        </td>
                        <td class="text-center text-nowrap">
                            <a href="{{ route('roles.edit', $role) }}" class="mera-action-btn mera-edit-btn"
                                title="Editar" aria-label="Editar rol {{ $role->name }}">
                                <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                            </a>
                            @unless ($isSystem || $role->users_count > 0)
                            @include('partials.delete-button', [
                                'action' => route('roles.destroy', $role),
                                'label' => "Eliminar rol {$role->name}",
                                'confirm' => "¿Eliminar el rol «{$role->name}»?",
                            ])
                            @endunless
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4">No hay roles registrados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
