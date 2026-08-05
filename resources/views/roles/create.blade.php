@extends('layouts.app')

@section('title', 'Nuevo Rol')

@section('content')

<div class="card mera-form-card">

    <div class="card-header mera-form-header">

        <div class="mera-header-icon">
            <i class="fa fa-users"></i>
        </div>

        <div>
            <h5>Crear Rol</h5>
            <span>Registra un nuevo rol y asigna sus permisos</span>
        </div>

    </div>

    <div class="card-block">

        <form action="{{ route('roles.store') }}" method="POST">

            @csrf

            <div class="row">

                <div class="col-md-12">

                    <div class="form-group mera-form-group">

                        <label>
                            Nombre del Rol
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control mera-input"
                            placeholder="Ingrese el nombre del rol"
                            required>

                    </div>

                </div>

            </div>


            <div class="form-group mera-form-group">

                <label>
                    Permisos
                </label>

                <div class="row">

                    @foreach($permissions as $permission)

                    <div class="col-md-4 col-sm-6 mb-2">

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="permission{{ $permission->id }}"
                                name="permissions[]"
                                value="{{ $permission->name }}">

                            <label
                                class="form-check-label"
                                for="permission{{ $permission->id }}">

                                {{ $permission->name }}

                            </label>

                        </div>

                    </div>

                    @endforeach

                </div>

            </div>


            <div class="mera-form-actions">

                <button
                    type="submit"
                    class="btn mera-btn-save">

                    <i class="fas fa-save"></i>

                    Crear

                </button>

                <a
                    href="{{ route('roles.index') }}"
                    class="btn mera-btn-cancel">

                    <i class="fas fa-times"></i>

                    Cancelar

                </a>

            </div>

        </form>

    </div>

</div>

@endsection