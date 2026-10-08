@if ($course->category_id && isset($categoryPaths[$course->category_id]))
<span class="mera-badge">{{ $categoryPaths[$course->category_id] }}</span>
@else
<span class="text-muted">Sin categoría</span>
@endif
