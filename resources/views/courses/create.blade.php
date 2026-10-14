@extends('layouts.app')

@section('title', 'Nuevo módulo')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-book" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Subir módulo</h1>
            <span>Sube el paquete ZIP de un módulo</span>
        </div>
    </div>

    <div class="card-block">
        @include('courses._form', [
            'action' => route('courses.store'),
            'method' => 'POST',
            'submitLabel' => 'Subir módulo',
        ])
    </div>
</div>
@endsection
