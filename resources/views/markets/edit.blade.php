@extends('layouts.app')

@section('title', 'Editar versión')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-globe" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Editar versión</h1>
            <span>{{ $market->name }}</span>
        </div>
    </div>
    <div class="card-block">
        @include('markets._form', ['action' => route('markets.update', $market), 'method' => 'PUT', 'submitLabel' => 'Guardar'])
    </div>
</div>
@endsection
