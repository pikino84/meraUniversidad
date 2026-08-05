@extends('layouts.app')

@section('title', 'Roles')

@section('content')

<div class="page-header mera-page-header">

    <div class="row align-items-center">

        <div class="col-md-8">

            <div class="header-title-wrapper">

                <span class="header-line"></span>

                <div>

                    <h5 class="mera-title">
                        Roles
                    </h5>

                    <p class="mera-subtitle">
                        Administración de roles y permisos del sistema
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

            <a href="{{ route('roles.create') }}" class="btn mera-btn-primary">

                <span class="mera-btn-icon">
                    +
                </span>

                Nuevo Rol

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

                        <th>Rol</th>

                        <th>Permisos</th>

                        <th class="text-center">
                            Acciones
                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse ($roles as $role)

                    <tr>

                        <td>

                            <div class="user-name">

                                {{ $role->name }}

                            </div>

                        </td>


                        <td>

                            @forelse ($role->permissions as $permission)

                            <span class="mera-badge">

                                {{ $permission->name }}

                            </span>

                            @empty

                            <span class="text-muted">

                                Sin permisos

                            </span>

                            @endforelse

                        </td>


                        <td class="text-center">

                            <a
                                href="{{ route('roles.edit', $role) }}"
                                class="mera-action-btn mera-edit-btn">

                                <i class="fas fa-pencil-alt"></i>

                            </a>


                            <form
                                action="{{ route('roles.destroy', $role) }}"
                                method="POST"
                                style="display:inline;"
                                id="delete-form-{{ $role->id }}">

                                @csrf
                                @method('DELETE')

                                <button
                                    type="button"
                                    class="mera-action-btn mera-delete-btn"
                                    onclick="confirmDelete({{ $role->id }})">

                                    <i class="fa fa-trash"></i>

                                </button>

                            </form>

                        </td>

                    </tr>

                    @empty

                    <tr>

                        <td colspan="3" class="text-center">

                            No hay roles registrados.

                        </td>

                    </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

</div>


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

@endsection