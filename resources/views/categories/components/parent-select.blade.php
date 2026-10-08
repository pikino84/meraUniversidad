{{-- Obsoleto: usar categories.components.select. Se mantiene por compatibilidad. --}}
@include('categories.components.select', [
    'name' => 'parent_id',
    'placeholder' => '-- Categoría principal --',
])
