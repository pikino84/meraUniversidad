@extends('layouts.app') {{-- Usa tu layout base --}}

@section('content')
<div class="container-fluid">
    <h1 class="mb-4">Editar Curso: {{ $course->name }}</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Ups!</strong> Hay algunos problemas con tus entradas.<br><br>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('courses.update', $course->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Datos del Curso</h6>
            </div>
            <div class="card-body">

                <div class="form-group">
                    <label for="name">Nombre del Curso <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $course->name) }}" required>
                </div>

                <div class="form-group mt-3">
                    <label for="description">Descripción <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="4" required>{{ old('description', $course->description) }}</textarea>
                </div>

                <div class="form-group mt-3">
                    <label for="cover_image">Imagen de Portada</label>
                    <div class="mb-3">
                        <img src="{{ asset('storage/' . $course->cover_image) }}" alt="Portada actual" width="200" class="img-thumbnail">
                    </div>
                    <input type="file" name="cover_image" class="form-control-file" accept="image/*">
                    <small class="form-text text-muted">Si quieres cambiar la imagen, sube una nueva.</small>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">Actualizar Curso</button>
                    <a href="{{ route('courses.index') }}" class="btn btn-secondary">Cancelar</a>
                </div>

            </div>
        </div>
    </form>
</div>
@endsection
