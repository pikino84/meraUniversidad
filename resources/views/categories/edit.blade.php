@extends('layouts.app')

@section('title', 'Editar categoría')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-folder-open" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Editar categoría</h1>
            <span>{{ $category->full_path }}</span>
        </div>
    </div>
    <div class="card-block">
        @include('categories._form', [
            'action' => route('categories.update', $category),
            'method' => 'PUT',
            'selectedParent' => $category->parent_id,
            'submitLabel' => 'Guardar cambios',
        ])
    </div>
</div>
@endsection
