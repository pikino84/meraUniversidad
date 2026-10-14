@if ($course->course_group_id && $course->group)
<a href="{{ route('course-groups.show', [$course->group, 'idioma' => $course->language]) }}" class="mera-badge text-decoration-none">
    <i class="fas fa-folder" aria-hidden="true"></i> {{ $course->group->name }}
</a>
<small class="text-muted d-block mt-1 mera-nowrap">
    {{ \App\Models\CourseGroup::languageLabel($course->language) }}@if ($course->position) · Módulo {{ $course->position }}@endif
</small>
@else
<span class="text-muted">Sin carpeta</span>
@endif
