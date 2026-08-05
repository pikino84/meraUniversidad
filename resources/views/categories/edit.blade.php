@extends('layouts.app')

@section('title', 'Editar Categoría')

@section('content')

<div class="card mera-form-card">

    <div class="card-header mera-form-header">

        <div class="mera-header-icon">
            <i class="fa fa-folder-open"></i>
        </div>

        <div>
            <h5>Editar Categoría</h5>
            <span>Actualiza la información de la categoría seleccionada</span>
        </div>

    </div>

    <div class="card-block">

        @if ($errors->any())

        <div class="alert alert-danger">

            <strong>Ups.</strong>

            Se encontraron algunos errores:

            <ul class="mb-0 mt-2">

                @foreach($errors->all() as $error)

                <li>{{ $error }}</li>

                @endforeach

            </ul>

        </div>

        @endif


        <form action="{{ route('categories.update', $category) }}" method="POST">

            @csrf
            @method('PUT')

            <div class="row">

                <div class="col-md-12">

                    <div class="form-group mera-form-group">

                        <label>

                            Nombre de la categoría

                            <span class="text-danger">*</span>

                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control mera-input"
                            value="{{ old('name', $category->name) }}"
                            placeholder="Ingrese el nombre de la categoría"
                            required>

                    </div>

                </div>


                <div class="col-md-12">

                    <div class="form-group mera-form-group">

                        <label>

                            Categoría padre

                        </label>

                        @include('categories.components.parent-select', [
                        'categories' => $categories,
                        'selected' => old('parent_id', $category->parent_id),
                        'excludedIds' => $excludedIds,
                        ])

                        <small class="text-muted">

                            No puedes seleccionar esta misma categoría ni ninguna de sus subcategorías como categoría padre.

                        </small>

                    </div>

                </div>

            </div>


            <div class="mera-form-actions">

                <button
                    type="submit"
                    class="btn mera-btn-save">

                    <i class="fas fa-save"></i>

                    Guardar cambios

                </button>

                <a
                    href="{{ route('categories.index') }}"
                    class="btn mera-btn-cancel">

                    <i class="fas fa-times"></i>

                    Cancelar

                </a>

            </div>

        </form>

    </div>

</div>

@endsection