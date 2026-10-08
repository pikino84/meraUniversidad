@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
@include('partials.page-header', [
    'title' => 'Usuarios',
    'subtitle' => 'Administración y gestión de usuarios del sistema',
    'action' => ['url' => route('users.create'), 'label' => 'Nuevo usuario'],
])

<div class="card mera-table-card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('users.index') }}" role="search" class="row g-2 align-items-end">
            <div class="col-md-9">
                <label for="user-search" class="form-label">Buscar por nombre o correo</label>
                <input id="user-search" type="search" name="search" value="{{ $search }}" class="form-control mera-input" placeholder="Ej. juan@meracorporation.com">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn mera-btn-primary flex-fill"><i class="fa fa-search" aria-hidden="true"></i> Buscar</button>
                @if ($search !== '')
                <a href="{{ route('users.index') }}" class="btn mera-btn-cancel" aria-label="Limpiar búsqueda" title="Limpiar búsqueda"><i class="fa fa-times" aria-hidden="true"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="mera-results-bar"><span>{{ $users->total() }} {{ $users->total() === 1 ? 'usuario' : 'usuarios' }}</span></div>

<div class="card mera-table-card">
    <div class="card-block table-border-style">
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Rol</th>
                        <th scope="col" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                    <tr>
                        <td data-label="Nombre">
                            <div class="user-name">
                                {{ $user->name }}
                                @if ($user->is(auth()->user()))
                                <small class="text-muted">(tú)</small>
                                @endif
                            </div>
                        </td>
                        <td data-label="Correo"><span class="user-email">{{ $user->email }}</span></td>
                        <td data-label="Rol">
                            @forelse ($user->roles as $role)
                            <span class="mera-badge">{{ $role->name }}</span>
                            @empty
                            <span class="text-muted">Sin rol</span>
                            @endforelse
                        </td>
                        <td class="text-center text-nowrap">
                            @can('update', $user)
                            <a href="{{ route('users.edit', $user) }}" class="mera-action-btn mera-edit-btn"
                                title="Editar" aria-label="Editar usuario {{ $user->name }}">
                                <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                            </a>
                            @endcan
                            @can('delete', $user)
                            @include('partials.delete-button', [
                                'action' => route('users.destroy', $user),
                                'label' => "Eliminar usuario {$user->name}",
                                'confirm' => "¿Eliminar a {$user->name}?",
                                'detail' => 'Perderá el acceso al sistema. Esta acción no se puede deshacer.',
                            ])
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            {{ $search !== '' ? 'No hay usuarios que coincidan con la búsqueda.' : 'No hay usuarios registrados.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $users->links() }}</div>
    </div>
</div>
@endsection
