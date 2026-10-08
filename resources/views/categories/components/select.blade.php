{{--
    Select jerárquico de categorías (único componente para curso, filtro y categoría padre).
    Parámetros: categories (árbol), name, id, selected, placeholder, excludedIds (opcional)
--}}
@php
    $excludedIds = $excludedIds ?? collect();
@endphp
<select name="{{ $name ?? 'category_id' }}" id="{{ $id ?? ($name ?? 'category_id') }}"
    class="form-control form-select mera-input @error($name ?? 'category_id') is-invalid @enderror">
    <option value="">{{ $placeholder ?? '-- Sin categoría --' }}</option>
    @foreach ($categories as $category)
        @include('categories.components.select-option', [
            'category' => $category,
            'level' => 0,
            'selected' => $selected ?? null,
            'excludedIds' => $excludedIds,
        ])
    @endforeach
</select>
