{{-- Obsoleto: usar categories.components.select. Se mantiene por compatibilidad. --}}
@include('categories.components.select', [
    'name' => 'category_id',
    'selected' => request('category_id'),
    'placeholder' => 'Todas las categorías',
])
