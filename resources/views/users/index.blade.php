@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
<div class="page-header mera-page-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="header-title-wrapper">
                <span class="header-line"></span>
                <div>
                    <h5 class="mera-title">Usuarios</h5>
                    <p class="mera-subtitle">
                        Administración y gestión de usuarios del sistema
                    </p>
                </div>
            </div>

            @if (session('success'))
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    swal("¡Éxito!", "{{ session('success') }}", "success");
                });
            </script>
            @elseif (session('error'))
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    swal("¡Error!", "{{ session('error') }}", "error");
                });
            </script>
            @endif

        </div>

        <div class="col-md-4 text-right text-md-right">
            <a href="{{ route('users.create') }}" class="btn mera-btn-primary">
                <span class="mera-btn-icon">+</span>
                Nuevo Usuario
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
                        <th>Email</th>
                        <th>Roles</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($users as $user)

                    <tr>

                        <td>
                            <div class="user-name">
                                {{ $user->name }}
                            </div>
                        </td>

                        <td>
                            <span class="user-email">
                                {{ $user->email }}
                            </span>
                        </td>


                        <td>

                            @foreach ($user->roles as $role)

                            <span class="mera-badge">
                                {{ $role->name }}
                            </span>

                            @endforeach

                        </td>


                        <td class="text-center">

                            <a href="{{ route('users.edit', $user) }}"
                                class="mera-action-btn mera-edit-btn">

                                <i class="fas fa-pencil-alt"></i>

                            </a>


                            <form action="{{ route('users.destroy', $user) }}"
                                method="POST"
                                style="display:inline;"
                                id="delete-form-{{ $user->id }}">

                                @csrf
                                @method('DELETE')


                                <button type="button"
                                    class="mera-action-btn mera-delete-btn"
                                    onclick="confirmDelete({{ $user->id }})">

                                    <i class="fa fa-trash"></i>

                                </button>

                            </form>


                            <script>
                                function confirmDelete(userId) {
                                    swal({
                                        title: "¿Estás seguro?",
                                        text: "Esta acción no se puede deshacer.",
                                        icon: "warning",
                                        buttons: ["Cancelar", "Eliminar"],
                                        dangerMode: true,
                                    }).then((willDelete) => {
                                        if (willDelete) {
                                            document.getElementById(`delete-form-${userId}`).submit();
                                        }
                                    });
                                }
                            </script>


                        </td>

                    </tr>


                    @empty

                    <tr>
                        <td colspan="4" class="text-center">
                            No hay usuarios registrados.
                        </td>
                    </tr>

                    @endforelse


                </tbody>

            </table>

        </div>

    </div>

</div>
@endsection