@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h1 class="mb-4">Crear Nuevo Curso</h1>

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

    <form action="{{ route('courses.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="card shadow mb-4">
            <div class="card-body">

                <div class="form-group">
                    <label for="name">Nombre del Curso <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Nombre del Curso" required>
                </div>

                <div class="form-group mt-3">
                    <label for="description">Descripción <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Descripción del curso" required></textarea>
                </div>

                <div class="form-group mt-3">
                    <label for="cover_image">Imagen de Portada <span class="text-danger">*</span></label>
                    <input type="file" name="cover_image" class="form-control-file" accept="image/*" required>
                </div>

                <div class="form-group mt-3">
                    <label for="zip_file">Archivo ZIP del Curso <span class="text-danger">*</span></label>
                    <input type="file" name="zip_file" class="form-control-file" accept=".zip" >
                    <small class="form-text text-muted">El archivo puede pesar hasta 1GB.</small>
                </div>
                <div class="form-group mt-4">
                    <button type="submit" class="btn btn-primary">Crear Curso</button>
                    <a href="{{ route('courses.index') }}" class="btn btn-secondary">Cancelar</a>
                </div>

            </div>
        </div>
    </form>
</div>
@endsection
