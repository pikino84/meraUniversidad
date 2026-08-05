@if(!$excludedIds->contains($category->id))

<option
    value="{{ $category->id }}"
    {{ (string) $selected === (string) $category->id ? 'selected' : '' }}>

    {{ str_repeat('— ', $level) }}{{ $category->name }}

</option>


@if($category->childrenRecursive->count())

    @foreach($category->childrenRecursive as $child)

        @include(
            'categories.components.parent-option',
            [
                'category' => $child,
                'level' => $level + 1,
                'selected' => $selected,
                'excludedIds' => $excludedIds,
            ]
        )

    @endforeach

@endif

@endif