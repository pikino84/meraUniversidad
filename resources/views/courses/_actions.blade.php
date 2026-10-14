@php $contentMissing = ($missing[$course->id]['content'] ?? false); @endphp
@if ($course->content_url && ! $contentMissing)
<a href="{{ $course->content_url }}" target="_blank" rel="noopener"
    class="mera-action-btn mera-add-btn" title="Ver módulo" aria-label="Ver módulo {{ $course->name }} (nueva pestaña)">
    <i class="fa fa-eye" aria-hidden="true"></i>
</a>
@elseif ($contentMissing)
<span class="mera-action-btn mera-btn-disabled" role="img" title="El contenido de este módulo no está en el servidor"
    aria-label="No se puede ver: el contenido de {{ $course->name }} no está en el servidor">
    <i class="fa fa-eye-slash" aria-hidden="true"></i>
</span>
@endif
<a href="{{ route('courses.edit', $course) }}" class="mera-action-btn mera-edit-btn"
    title="Editar" aria-label="Editar módulo {{ $course->name }}">
    <i class="fas fa-pencil-alt" aria-hidden="true"></i>
</a>
@include('partials.delete-button', [
    'action' => route('courses.destroy', $course),
    'label' => "Eliminar módulo {$course->name}",
    'confirm' => '¿Eliminar el módulo?',
    'strong' => $course->name,
    'detail' => 'Su link deja de funcionar. Un super administrador puede restaurarlo desde «Papelera» durante ' . config('mera.courses.trash_retention_days') . ' días.',
])
