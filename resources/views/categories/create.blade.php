@extends('layouts.app')

@section('title','Nueva Categoría')

@section('content')

<div class="card mera-form-card">

    <div class="card-header mera-form-header">

        <div class="mera-header-icon">
            <i class="fa fa-folder"></i>
        </div>

        <div>

            <h5>
                Crear Categoría
            </h5>

            <span>
                Registra una nueva categoría para organizar tus cursos
            </span>

        </div>

    </div>


    <div class="card-block">


        @if ($errors->any())

        <div class="alert alert-danger">

            <strong>
                Ups.
            </strong>

            Se encontraron algunos errores:

            <ul class="mb-0 mt-2">

                @foreach($errors->all() as $error)

                <li>
                    {{ $error }}
                </li>

                @endforeach

            </ul>

        </div>

        @endif



        <form action="{{ route('categories.store') }}" method="POST">

            @csrf


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
                            value="{{ old('name') }}"
                            placeholder="Ejemplo: Programación"
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

                        'selected' => old('parent_id', $parent ?? null),

                        'excludedIds' => collect(),

                        ])



                        <small class="text-muted">

                            Si seleccionas una categoría padre, esta será creada como subcategoría.

                        </small>



                    </div>


                </div>


            </div>




            <div class="mera-form-actions">


                <button
                    type="submit"
                    class="btn mera-btn-save">


                    <i class="fas fa-save"></i>

                    Guardar categoría


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