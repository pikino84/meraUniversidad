@extends('layouts.app')

@section('title', 'Salas')

@section('content')
<div class="page-header">
    <div class="row">
        <div class="col-md-12">
            <h5>Salas Lounge</h5>
            @if (session('success'))                
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    swal("¡Éxito!", "{{ session('success') }}", "success");
                });
            </script>
            @elseif (session('error'))
            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    swal("¡Error!", "{{ session('error') }}", "error");
                });
            </script>
            @endif
            <a href="{{ route('lounges.create') }}" class="btn btn-primary float-right mb-3">Nueva Sala</a>
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
                        <th>Terminal</th>
                        <th>Capacidad</th>
                        <th>Estatus</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>            
                    @forelse ($lounges as $lounge)
                        <tr>
                            <td>{{ $lounge->name }}</td>
                            <td>{{ $lounge->terminal }}</td>
                            <td>{{ $lounge->capacity }}</td>
                            <td>{{ $lounge->status ? 'Activo' : 'Inactivo' }}</td>
                            <td>
                                <a href="{{ route('lounges.edit', $lounge) }}" class="btn btn-warning btn-sm">Editar</a>
                                <form action="{{ route('lounges.destroy', $lounge) }}" method="POST" style="display:inline;" id="delete-form-{{ $lounge->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete({{ $lounge->id }})">Eliminar</button>
                                </form>
                                <script>
                                    function confirmDelete(loungeId) {
                                        swal({
                                            title: "¿Estás seguro?",
                                            text: "Esta acción no se puede deshacer.",
                                            icon: "warning",
                                            buttons: ["Cancelar", "Eliminar"],
                                            dangerMode: true,
                                        }).then((willDelete) => {
                                            if (willDelete) {
                                                document.getElementById(`delete-form-${loungeId}`).submit();
                                            }
                                        });
                                    }
                                </script>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No hay salas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
