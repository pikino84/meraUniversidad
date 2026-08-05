<select
    name="parent_id"
    class="form-control mera-input">

    <option value="">
        -- Categoría principal --
    </option>


    @foreach($categories as $category)

    @include(
    'categories.components.parent-option',
    [
    'category' => $category,
    'level' => 0,
    'selected' => $selected,
    'excludedIds' => $excludedIds,
    ]
    )

    @endforeach


</select>