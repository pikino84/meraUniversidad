@extends('layouts.app')

@section('title', 'Nueva carpeta de curso')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-folder" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Nueva carpeta de curso</h1>
            <span>Una carpeta por curso; dentro, sus módulos en español e inglés</span>
        </div>
    </div>
    <div class="card-block">
        @include('course-groups._form', ['action' => route('course-groups.store'), 'method' => 'POST', 'submitLabel' => 'Crear carpeta'])
    </div>
</div>
@endsection
