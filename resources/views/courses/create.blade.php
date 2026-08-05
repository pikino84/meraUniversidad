@extends('layouts.app')

@section('title', 'Nuevo Curso')

@section('content')

<div class="card mera-form-card">

    <div class="card-header mera-form-header">

        <div class="mera-header-icon">
            <i class="fa fa-book"></i>
        </div>

        <div>
            <h5>Crear Curso</h5>
            <span>Registra un nuevo curso dentro del sistema</span>
        </div>

    </div>


    <div class="card-block">

        @if ($errors->any())

        <div class="alert alert-danger">

            <strong>
                Ups!
            </strong>

            Hay algunos problemas con tus entradas:

            <ul>
                @foreach ($errors->all() as $error)

                <li>
                    {{ $error }}
                </li>

                @endforeach
            </ul>

        </div>

        @endif



        <form id="cursos_form"
            action="{{ route('courses.store') }}"
            method="POST"
            enctype="multipart/form-data">

            @csrf


            <div class="row">


                <div class="col-md-6">

                    <div class="form-group mera-form-group">

                        <label>
                            Nombre del Curso
                            <span class="text-danger">*</span>
                        </label>


                        <input
                            type="text"
                            name="name"
                            class="form-control mera-input"
                            placeholder="Nombre del Curso"
                            value="{{ old('name') }}"
                            required>

                    </div>

                </div>



                <div class="col-md-6">

                    <div class="form-group mera-form-group">

                        <label>
                            Categoría
                        </label>


                        @include('categories.components.select', [
                        'categories'=>$categories,
                        'selected'=>old('category_id')
                        ])


                        <small class="form-text text-muted">
                            Selecciona la categoría a la que pertenecerá este curso.
                        </small>

                    </div>

                </div>



                <div class="col-md-12">

                    <div class="form-group mera-form-group">

                        <label>
                            Descripción
                            <span class="text-danger">*</span>
                        </label>


                        <textarea
                            name="description"
                            class="form-control mera-input"
                            rows="5"
                            placeholder="Descripción del curso"
                            required>{{ old('description') }}</textarea>


                    </div>

                </div>



                <div class="col-md-6">

                    <div class="form-group mera-form-group">

                        <label>
                            Imagen de Portada
                            <span class="text-danger">*</span>
                        </label>

                        <br>

                        <input
                            type="file"
                            name="cover_image"
                            class="form-control-file"
                            accept="image/*"
                            required>


                    </div>

                </div>



                <div class="col-md-6">

                    <div class="form-group mera-form-group">

                        <label>
                            Archivo ZIP del Curso
                            <span class="text-danger">*</span>
                        </label>

                        <br>

                        <input
                            type="file"
                            name="zip_file"
                            class="form-control-file"
                            accept=".zip"
                            required>

                        <br>

                        <small class="form-text text-muted">
                            El archivo puede pesar hasta 1GB.
                        </small>


                    </div>

                </div>


            </div>



            <div class="mera-form-actions">


                <button type="submit"
                    class="btn mera-btn-save">

                    <i class="fa fa-save"></i>
                    Crear Curso

                </button>



                <a href="{{ route('courses.index') }}"
                    class="btn mera-btn-cancel">

                    <i class="fa fa-times"></i>
                    Cancelar

                </a>


            </div>


        </form>


    </div>

</div>



<div id="loadingOverlay">

    <div>

        <div class="spinner-border text-light mb-3"
            role="status"></div>


        <div>
            Procesando curso, no refresques la página, por favor espera...
        </div>

    </div>

</div>


@endsection



@push('scripts')

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const cursos_form = document.getElementById('cursos_form');
        const overlay = document.getElementById('loadingOverlay');


        cursos_form.addEventListener('submit', function() {

            overlay.style.display = 'flex';

        });

    });
</script>

@endpush