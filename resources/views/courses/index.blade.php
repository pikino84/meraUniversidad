@extends('layouts.app')

@section('title', 'Cursos')

@section('content')
<div class="page-header">
    <div class="row">
        <div class="col-md-12">
            <h5>Cursos</h5>
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
            <a href="{{ route('courses.create') }}" class="btn btn-primary float-right mb-3">Nuevo curso</a>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-block table-border-style">
        <div class="table-responsive">
            <table class="table table-striped table-xs table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Portada</th>                        
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>            
                    @forelse ($courses as $course)
                        <tr>
                            <td>{{ $course->name }}</td>
                            <td>
                                <img src="{{ asset('storage/' . $course->cover_image) }}" alt="Portada" width="100" class="img-thumbnail">
                            </td>
                            <td>
                                <a href="{{ route('courses.edit', $course) }}" class="btn btn-warning btn-sm">Editar</a>
                                <form action="{{ route('courses.destroy', $course) }}" method="POST" style="display:inline;" id="delete-form-{{ $course->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete({{ $course->id }})">Eliminar</button>
                                </form>
                                <script>
                                    function confirmDelete(courseId) {
                                        swal({
                                            title: "¿Estás seguro?",
                                            text: "Esta acción no se puede deshacer.",
                                            icon: "warning",
                                            buttons: ["Cancelar", "Eliminar"],
                                            dangerMode: true,
                                        }).then((willDelete) => {
                                            if (willDelete) {
                                                document.getElementById(`delete-form-${courseId}`).submit();
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
