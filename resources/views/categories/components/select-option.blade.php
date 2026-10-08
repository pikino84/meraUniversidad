@unless ($excludedIds->contains($category->id))
<option value="{{ $category->id }}" @selected((string) $selected === (string) $category->id)>
    {{ str_repeat('— ', $level) }}{{ $category->name }}
</option>
@foreach ($category->childrenRecursive as $child)
    @include('categories.components.select-option', [
        'category' => $child,
        'level' => $level + 1,
        'selected' => $selected,
        'excludedIds' => $excludedIds,
    ])
@endforeach
@endunless
