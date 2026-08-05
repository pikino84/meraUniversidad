<select
    name="category_id"
    class="form-control mera-input">

    <option value="">
        Todas las categorías
    </option>


    @foreach($categories as $category)

    @include(
    'categories.components.filter-option',
    [
    'category' => $category,
    'level' => 0,
    'selected' => request('category_id')
    ]
    )

    @endforeach


</select>