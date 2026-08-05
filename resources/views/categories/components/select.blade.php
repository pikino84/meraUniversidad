<select
    name="category_id"
    class="form-control mera-input">

    <option value="">
        -- Sin categoría --
    </option>


    @foreach($categories as $category)

    @include(
    'categories.components.select-option',
    [
    'category' => $category,
    'level' => 0,
    'selected' => $selected ?? null
    ]
    )

    @endforeach


</select>