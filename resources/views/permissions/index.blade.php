@extends('layouts.app')

@section('title', 'Permisos')

@section('content')

<div class="page-header mera-page-header">

    <div class="row align-items-center">

        <div class="col-md-8">

            <div class="header-title-wrapper">

                <span class="header-line"></span>

                <div>

                    <h5 class="mera-title">
                        Permisos
                    </h5>

                    <p class="mera-subtitle">
                        Administración y gestión de permisos del sistema
                    </p>

                </div>

            </div>


            @if (session('success'))

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    swal("¡Éxito!", "{{ session('success') }}", "success");
                });
            </script>

            @endif

        </div>


        <div class="col-md-4 text-right">

            <a href="{{ route('permissions.create') }}"
                class="btn mera-btn-primary">

                <span class="mera-btn-icon">+</span>

                Nuevo Permiso

            </a>

        </div>

    </div>

</div>



<div class="card mera-table-card">

    <div class="card-block table-border-style">

        <div class="table-responsive">

            <table class="table mera-table table-striped table-hover">

                <thead>

                    <tr>

                        <th>Nombre</th>

                        <th class="text-center">
                            Acciones
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse ($permissions as $permission)

                    <tr>

                        <td>

                            <div class="user-name">
                                {{ $permission->name }}
                            </div>

                        </td>


                        <td class="text-center">

                            <a href="{{ route('permissions.edit', $permission) }}"
                                class="mera-action-btn mera-edit-btn">

                                <i class="fas fa-pencil-alt"></i>

                            </a>


                            <form
                                action="{{ route('permissions.destroy', $permission) }}"
                                method="POST"
                                style="display:inline;"
                                id="delete-form-{{ $permission->id }}">

                                @csrf
                                @method('DELETE')

                                <button
                                    type="button"
                                    class="mera-action-btn mera-delete-btn"
                                    onclick="confirmDelete({{ $permission->id }})">

                                    <i class="fa fa-trash"></i>

                                </button>

                            </form>

                        </td>

                    </tr>

                    @empty

                    <tr>

                        <td colspan="2" class="text-center">

                            No hay permisos registrados.

                        </td>

                    </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>



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

@endsection