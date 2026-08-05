@extends('layouts.app')

@section('title', 'Crear Permiso')

@section('content')

<div class="card mera-form-card">

    <div class="card-header mera-form-header">

        <div class="mera-header-icon">
            <i class="fa fa-key"></i>
        </div>

        <div>
            <h5>Crear Permiso</h5>
            <span>Registra un nuevo permiso en el sistema</span>
        </div>

    </div>


    <div class="card-block">

        <form action="{{ route('permissions.store') }}" method="POST">

            @csrf


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

                    <i class="fas fa-plus-circle"></i>
                    Crear Permiso

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