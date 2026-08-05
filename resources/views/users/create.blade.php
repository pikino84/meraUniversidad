@extends('layouts.app')

@section('title', 'Nuevo Usuario')

@section('content')

<div class="card mera-form-card">

    <div class="card-header mera-form-header">

        <div class="mera-header-icon">
            <i class="fa fa-user-plus"></i>
        </div>

        <div>
            <h5>Crear Usuario</h5>
            <span>Registra un nuevo usuario en el sistema</span>
        </div>

    </div>


    <div class="card-block">

        <form action="{{ route('users.store') }}" method="POST">

            @csrf


            <div class="row">


                <div class="col-md-6">

                    <div class="form-group mera-form-group">

                        <label>
                            Nombre
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control mera-input"
                            placeholder="Ingrese el nombre"
                            required>

                    </div>

                </div>



                <div class="col-md-6">

                    <div class="form-group mera-form-group">

                        <label>
                            Correo Electrónico
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control mera-input"
                            placeholder="Ingrese el correo electrónico"
                            required>

                    </div>

                </div>



                <div class="col-md-6">

                    <div class="form-group mera-form-group">

                        <label>
                            Contraseña
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control mera-input"
                            placeholder="Ingrese la contraseña"
                            required>

                    </div>

                </div>



                <div class="col-md-6">

                    <div class="form-group mera-form-group">

                        <label>
                            Rol
                        </label>

                        <select
                            name="role"
                            class="form-control mera-input"
                            required>


                            @foreach ($roles as $role)

                            <option value="{{ $role->name }}">
                                {{ $role->name }}
                            </option>

                            @endforeach


                        </select>

                    </div>

                </div>


            </div>



            <div class="mera-form-actions">


                <button type="submit" class="btn mera-btn-save">

                    <i class="fas fa-user-plus"></i>
                    Crear Usuario

                </button>



                <a href="{{ route('users.index') }}"
                    class="btn mera-btn-cancel">

                    <i class="fas fa-times"></i>
                    Cancelar

                </a>


            </div>


        </form>

    </div>

</div>


@endsection