@extends('layouts.app')

@section('title', 'Categorías')

@section('content')

<div class="page-header mera-page-header">

    <div class="row align-items-center">

        <div class="col-md-8">

            <div class="header-title-wrapper">

                <span class="header-line"></span>

                <div>

                    <h5 class="mera-title">
                        Categorías
                    </h5>

                    <p class="mera-subtitle">
                        Organiza los cursos mediante categorías y subcategorías
                    </p>

                </div>

            </div>

        </div>


        <div class="col-md-4 text-right">

            <a
                href="{{ route('categories.create') }}"
                class="btn mera-btn-primary">

                <span class="mera-btn-icon">
                    +
                </span>

                Nueva Categoría

            </a>

        </div>

    </div>

</div>


@if (session('success'))

<script>
    document.addEventListener("DOMContentLoaded", function() {
        swal("¡Éxito!", "{{ session('success') }}", "success");
    });
</script>

@endif


@if (session('error'))

<script>
    document.addEventListener("DOMContentLoaded", function() {
        swal("¡Error!", "{{ session('error') }}", "error");
    });
</script>

@endif



<div class="card mera-table-card">

    <div class="card-block">

        @forelse($categories as $category)

        @include('categories.components.tree-item',[
        'category'=>$category,
        'level'=>0
        ])

        @empty

        <div class="text-center py-5">

            <i
                class="fa fa-folder-open"
                style="font-size:55px;color:#81CFF4;">
            </i>

            <h5 class="mt-3">

                No existen categorías registradas.

            </h5>

            <p class="text-muted">

                Comienza creando la primera categoría.

            </p>

        </div>

        @endforelse

    </div>

</div>

@endsection


@push('scripts')

<script>
    function confirmDelete(categoryId) {

        swal({

            title: "¿Estás seguro?",

            text: "Eliminar una categoría puede afectar sus cursos relacionados.",

            icon: "warning",

            buttons: ["Cancelar", "Eliminar"],

            dangerMode: true

        }).then((willDelete) => {

            if (willDelete) {

                document.getElementById(`delete-form-${categoryId}`).submit();

            }

        });

    }
</script>

@endpush