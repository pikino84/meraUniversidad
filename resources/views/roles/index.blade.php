@extends('layouts.app')

@section('title', 'Roles')

@section('content')
<div class="page-header">
    <div class="row">
        <div class="col-md-12">
            <h5>Roles</h5>
            @if (session('success'))                
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    swal("¡Éxito!", "{{ session('success') }}", "success");
                });
            </script>
            @endif
            <a href="{{ route('roles.create') }}" class="btn btn-primary float-right mb-3">Nuevo Rol</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-block table-border-style">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Permisos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td>{{ $role->name }}</td>
                            <td>
                                @forelse ($role->permissions as $permission)
                                    <span class="badge badge-info">{{ $permission->name }}</span>
                                @empty
                                    <span class="text-muted">Sin permisos</span>
                                @endforelse
                            </td>
                            <td>
                                <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-warning">Editar</a>
                                <form action="{{ route('roles.destroy', $role) }}" method="POST" style="display:inline;" id="delete-form-{{ $role->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete({{ $role->id }})">Eliminar</button>
                                </form>
                                
                                <script>
                                    function confirmDelete(roleId) {
                                        swal({
                                            title: "¿Estás seguro?",
                                            text: "Esta acción no se puede deshacer.",
                                            icon: "warning",
                                            buttons: ["Cancelar", "Eliminar"],
                                            dangerMode: true,
                                        }).then((willDelete) => {
                                            if (willDelete) {
                                                document.getElementById(`delete-form-${roleId}`).submit();
                                            }
                                        });
                                    }
                                </script>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">No hay roles registrados.</td>
                        </tr>
                    @endforelse
                </tbody>                
            </table>
        </div>
    </div>
</div>
@endsection