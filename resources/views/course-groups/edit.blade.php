@extends('layouts.app')

@section('title', 'Editar carpeta')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-folder-open" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Editar carpeta</h1>
            <span>{{ $group->name }}</span>
        </div>
    </div>
    <div class="card-block">
        @include('course-groups._form', ['action' => route('course-groups.update', $group), 'method' => 'PUT', 'submitLabel' => 'Guardar'])
    </div>
</div>
@endsection
