@if ($filtering ?? false)
No hay módulos que coincidan con la búsqueda.
<a href="{{ route('courses.index') }}">Limpiar filtros</a>
@else
Aún no hay módulos registrados.
<a href="{{ route('courses.create') }}">Crea el primero</a>
@endif
