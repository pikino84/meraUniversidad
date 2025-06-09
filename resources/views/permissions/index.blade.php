@extends('layouts.app')

@section('title', 'Permisos')

@section('content')
<div class="page-header">
    <div class="row">
        <div class="col-md-12">
            <h5>Permisos</h5>
            @if (session('success'))                
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    swal("¡Éxito!", "{{ session('success') }}", "success");
                });
            </script>
            @endif
            <a href="{{ route('permissions.create') }}" class="btn btn-primary float-right mb-3">Nuevo Permiso</a>
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
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($permissions as $permission)
                        <tr>
                            <td>{{ $permission->name }}</td>
                            <td>
                                <a href="{{ route('permissions.edit', $permission) }}" class="btn btn-sm btn-warning">Editar</a>                                
                                <form action="{{ route('permissions.destroy', $permission) }}" method="POST" style="display:inline;" id="delete-form-{{ $permission->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete({{ $permission->id }})">Eliminar</button>
                                </form>
                                
                                <script>
                                    function confirmDelete(permissionId) {
                                        swal({
                                            title: "¿Estás seguro?",
                                            text: "Esta acción no se puede deshacer.",
                                            icon: "warning",
                                            buttons: ["Cancelar", "Eliminar"],
                                            dangerMode: true,
                                        }).then((willDelete) => {
                                            if (willDelete) {
                                                document.getElementById(`delete-form-${permissionId}`).submit();
                                            }
                                        });
                                    }
                                </script>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">No hay permisos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection