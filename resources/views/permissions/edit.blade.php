@extends('layouts.app')

@section('title', 'Editar Permiso')

@section('content')

<div class="card mera-form-card">

    <div class="card-header mera-form-header">

        <div class="mera-header-icon">
            <i class="fa fa-key"></i>
        </div>

        <div>
            <h5>Editar Permiso</h5>
            <span>Actualiza la información del permiso seleccionado</span>
        </div>

    </div>


    <div class="card-block">

        <form action="{{ route('permissions.update', $permission) }}" method="POST">

            @csrf
            @method('PUT')


            <div class="row">

                <div class="col-md-12">

                    <div class="form-group mera-form-group">

                        <label>
                            Nombre del Permiso
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control mera-input"
                            value="{{ $permission->name }}"
                            placeholder="Ingrese el nombre del permiso"
                            required>

                        @error('name')
                        <small class="text-danger">
                            {{ $message }}
                        </small>
                        @enderror

                    </div>

                </div>

            </div>



            <div class="mera-form-actions">

                <button
                    type="submit"
                    class="btn mera-btn-save">

                    <i class="fas fa-save"></i>
                    Actualizar

                </button>


                <a
                    href="{{ route('permissions.index') }}"
                    class="btn mera-btn-cancel">

                    <i class="fas fa-times"></i>
                    Cancelar

                </a>

            </div>

        </form>

    </div>

</div>

@endsection