@extends('layouts.app')

@section('title', 'Cursos')

@section('content')

<div class="page-header mera-page-header">

    <div class="row align-items-center">

        <div class="col-md-8">

            <div class="header-title-wrapper">

                <span class="header-line"></span>

                <div>

                    <h5 class="mera-title">
                        Cursos
                    </h5>

                    <p class="mera-subtitle">
                        Administración y gestión de cursos del sistema
                    </p>

                </div>

            </div>


            @if (session('success'))

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    swal("¡Éxito!", "{{ session('success') }}", "success");
                });
            </script>

            @elseif (session('error'))

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    swal("¡Error!", "{{ session('error') }}", "error");
                });
            </script>

            @endif


        </div>



        <div class="col-md-4 text-right">

            <a href="{{ route('courses.create') }}"
                class="btn mera-btn-primary">

                <span class="mera-btn-icon">
                    +
                </span>

                Nuevo curso

            </a>

        </div>


    </div>

</div>



<div class="card mera-table-card mb-3">


    <div class="card-body">


        <form method="GET"
            action="{{ route('courses.index') }}">


            <div class="row">


                <div class="col-md-5">

                    <div class="form-group mera-form-group">

                        <label>
                            Buscar curso
                        </label>


                        <input
                            type="text"
                            name="search"
                            class="form-control mera-input"
                            placeholder="Nombre del curso..."
                            value="{{ request('search') }}">


                    </div>


                </div>



                <div class="col-md-5">


                    <div class="form-group mera-form-group">


                        <label>
                            Categoría
                        </label>


                        @include('categories.components.filter-select',[
                        'categories'=>$categories
                        ])


                    </div>


                </div>



                <div class="col-md-2 d-flex align-items-end">


                    <button class="btn mera-btn-primary w-100 mb-3">


                        <i class="fa fa-search"></i>

                        Buscar


                    </button>


                </div>


            </div>


        </form>


    </div>


</div>





<div class="card mera-table-card">
    <div class="card-block table-border-style">
        <div class="table-responsive mera-table-desktop">
            <table class="table mera-table table-striped table-hover">
                <thead>
                    <tr>
                        <th>
                            Nombre
                        </th>
                        <th>
                            Categoría
                        </th>
                        <th>
                            Portada
                        </th>
                        <th class="text-center">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($courses as $course)
                    <tr>
                        <td>
                            <div class="user-name">
                                {{ $course->name }}
                            </div>
                        </td>

                        <td>
                            @if($course->category)
                            <span class="mera-badge">
                                {{ $course->category->full_path }}
                            </span>
                            @else
                            <span class="text-muted">
                                Sin categoría
                            </span>
                            @endif
                        </td>

                        <td>
                            <img
                                src="{{ asset('storage/' . $course->cover_image) }}"
                                alt="Portada"
                                width="100"
                                class="img-thumbnail">
                        </td>

                        <td class="text-center">
                            <a href="{{ route('courses.edit', $course) }}"
                                class="mera-action-btn mera-edit-btn">
                                <i class="fas fa-pencil-alt"></i>
                            </a>

                            <form
                                action="{{ route('courses.destroy', $course) }}"
                                method="POST"
                                style="display:inline;"
                                id="delete-form-{{ $course->id }}">

                                @csrf
                                @method('DELETE')

                                <button
                                    type="button"
                                    class="mera-action-btn mera-delete-btn"
                                    onclick="confirmDelete({{ $course->id }})">

                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>

                    @empty
                    <tr>
                        <td colspan="4" class="text-center">
                            No hay cursos registrados.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mera-courses-mobile">
            @forelse ($courses as $course)
            <div class="mera-course-card">
                <div class="mera-course-header">
                    <div class="user-name">
                        {{ $course->name }}
                    </div>
                </div>

                <div class="mera-course-cover">
                    <img
                        src="{{ asset('storage/' . $course->cover_image) }}"
                        alt="Portada"
                        class="img-thumbnail">
                </div>

                <div class="mera-course-info">
                    <strong>Categoría:</strong>

                    @if($course->category)
                    <span class="mera-badge">
                        {{ $course->category->full_path }}
                    </span>
                    @else

                    <span class="text-muted">
                        Sin categoría
                    </span>
                    @endif
                </div>

                <div class="mera-course-actions">
                    <a href="{{ route('courses.edit', $course) }}"
                        class="mera-action-btn mera-edit-btn">
                        <i class="fas fa-pencil-alt"></i>
                    </a>

                    <form
                        action="{{ route('courses.destroy', $course) }}"
                        method="POST"
                        id="delete-form-{{ $course->id }}">

                        @csrf
                        @method('DELETE')

                        <button
                            type="button"
                            class="mera-action-btn mera-delete-btn"
                            onclick="confirmDelete({{ $course->id }})">
                            <i class="fa fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>

            @empty
            <div class="text-center">
                No hay cursos registrados.
            </div>
            @endforelse
        </div>

        <div class="mt-3">
            {{ $courses->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function confirmDelete(courseId) {
        swal({
            title: "¿Estás seguro?",
            text: "Esta acción no se puede deshacer.",
            icon: "warning",
            buttons: ["Cancelar", "Eliminar"],
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                document
                    .getElementById(`delete-form-${courseId}`)
                    .submit();
            }
        });
    }
</script>
@endpush