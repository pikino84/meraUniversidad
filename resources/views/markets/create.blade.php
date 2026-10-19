@extends('layouts.app')

@section('title', 'Nueva versión')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-globe" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Nueva versión</h1>
            
        </div>
    </div>
    <div class="card-block">
        @include('markets._form', ['action' => route('markets.store'), 'method' => 'POST', 'submitLabel' => 'Crear versión'])
    </div>
</div>
@endsection
