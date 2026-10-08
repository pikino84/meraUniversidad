@if ($filtering ?? false)
No hay cursos que coincidan con la búsqueda.
<a href="{{ route('courses.index') }}">Limpiar filtros</a>
@else
Aún no hay cursos registrados.
<a href="{{ route('courses.create') }}">Crea el primero</a>
@endif
