@if ($course->content_url)
<a href="{{ $course->content_url }}" target="_blank" rel="noopener"
    class="mera-action-btn mera-add-btn" title="Ver curso" aria-label="Ver curso {{ $course->name }} (nueva pestaña)">
    <i class="fa fa-eye" aria-hidden="true"></i>
</a>
@endif
<a href="{{ route('courses.edit', $course) }}" class="mera-action-btn mera-edit-btn"
    title="Editar" aria-label="Editar curso {{ $course->name }}">
    <i class="fas fa-pencil-alt" aria-hidden="true"></i>
</a>
@include('partials.delete-button', [
    'action' => route('courses.destroy', $course),
    'label' => "Eliminar curso {$course->name}",
    'confirm' => "¿Eliminar el curso «{$course->name}»?",
    'detail' => 'El curso dejará de estar disponible. Sus archivos se conservan en la papelera durante ' . config('mera.courses.trash_retention_days') . ' días.',
])
