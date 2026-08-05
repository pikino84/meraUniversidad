@extends('layouts.app')

@section('title', 'Editar Curso')

@section('content')

<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon">
            <i class="fa fa-book"></i>
        </div>

        <div>
            <h5>
                Editar Curso
            </h5>

            <span>
                Actualiza la información del curso seleccionado
            </span>
        </div>
    </div>

    <div class="card-block">
        @if ($errors->any())
        <div class="alert alert-danger">

            <strong>
                Ups!
            </strong>

            Hay algunos problemas con tus entradas.

            <ul>
                @foreach ($errors->all() as $error)
                <li>
                    {{ $error }}
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        <form
            id="cursos_form"
            action="{{ route('courses.update', $course->id) }}"
            method="POST"
            enctype="multipart/form-data">

            @csrf
            @method('PUT')

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
                            value="{{ old('name', $course->name) }}"
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
                        'selected'=>old('category_id', $course->category_id)
                        ])

                        <small class="form-text text-muted">
                            Puedes cambiar la categoría del curso en cualquier momento.
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
                            rows="4"
                            required>{{ old('description', $course->description) }}
                        </textarea>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group mera-form-group">
                        <label>
                            Imagen de Portada
                        </label>

                        <div class="mb-3">
                            <img src="{{ asset('storage/' . $course->cover_image) }}" alt="Portada actual" width="200" class="img-thumbnail">
                        </div>

                        <input type="file" name="cover_image" class="form-control-file" accept="image/*">
                        <br>
                        <small class="form-text text-muted">
                            Si quieres cambiar la imagen, sube una nueva.
                        </small>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group mera-form-group">
                        <label>
                            Archivo ZIP del Curso
                        </label>

                        <input type="file" name="zip_file" class="form-control-file" accept=".zip">
                        <br>
                        <small class="form-text text-muted">
                            Si no subes un nuevo archivo ZIP, se conservará el contenido actual.
                        </small>
                    </div>
                </div>
            </div>

            <div class="mera-form-actions">
                <button type="submit" class="btn mera-btn-save">
                    <i class="fas fa-save"></i>
                    Actualizar Curso
                </button>

                <a href="{{ route('courses.index') }}"
                    class="btn mera-btn-cancel">
                    <i class="fas fa-times"></i>
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<div id="loadingOverlay">
    <div>
        <div class="spinner-border text-light mb-3" role="status"></div>
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