@if ($course->course_group_id && $course->group)
<a href="{{ $course->course_group_version_id ? route('course-groups.versions.show', [$course->group, $course->course_group_version_id]) : route('course-groups.show', $course->group) }}" class="mera-badge text-decoration-none">
    <i class="fas fa-folder" aria-hidden="true"></i> {{ $course->group->name }}
</a>
<small class="text-muted d-block mt-1 mera-nowrap">
    {{ $course->version ? $course->version->label() : 'Sin año ni versión' }}@if ($course->position) · Módulo {{ $course->position }}@endif
</small>
@else
<span class="text-muted">Sin carpeta</span>
@endif
