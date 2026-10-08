@extends('layouts.app')

@section('title', 'Categorías')

@section('content')
@include('partials.page-header', [
    'title' => 'Categorías',
    'subtitle' => 'Organiza los cursos mediante categorías y subcategorías',
    'action' => ['url' => route('categories.create'), 'label' => 'Nueva categoría'],
])

@php($flat = \App\Models\Category::flat())

<div class="card mera-table-card">
    <div class="card-block">
        @if ($categories->isEmpty())
        <div class="text-center py-5">
            <i class="fa fa-folder-open" style="font-size:55px;color:#81CFF4;" aria-hidden="true"></i>
            <h2 class="h5 mt-3">No existen categorías registradas.</h2>
            <p class="text-muted">
                Comienza creando la <a href="{{ route('categories.create') }}">primera categoría</a>.
            </p>
        </div>
        @else
        <ul class="list-unstyled mb-0" aria-label="Árbol de categorías">
            @foreach ($categories as $category)
                @include('categories.components.tree-item', ['category' => $category, 'flat' => $flat])
            @endforeach
        </ul>
        @endif
    </div>
</div>
@endsection
