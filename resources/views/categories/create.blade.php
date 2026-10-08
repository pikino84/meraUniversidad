@extends('layouts.app')

@section('title', 'Nueva categoría')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-folder" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Crear categoría</h1>
            <span>Registra una nueva categoría para organizar tus cursos</span>
        </div>
    </div>
    <div class="card-block">
        @include('categories._form', [
            'action' => route('categories.store'),
            'method' => 'POST',
            'selectedParent' => $parent,
            'excludedIds' => collect(),
            'submitLabel' => 'Guardar categoría',
        ])
    </div>
</div>
@endsection
