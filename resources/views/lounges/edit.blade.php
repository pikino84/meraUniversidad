@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Editar Sala</h1>

    <form method="POST" action="{{ route('lounges.update', $lounge) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="name" class="form-label">Nombre de la Sala</label>
            <input type="text" name="name" value="{{ $lounge->name }}" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="terminal" class="form-label">Terminal</label>
            <input type="text" name="terminal" value="{{ $lounge->terminal }}" class="form-control">
        </div>

        <div class="mb-3">
            <label for="capacity" class="form-label">Capacidad</label>
            <input type="number" name="capacity" value="{{ $lounge->capacity }}" class="form-control">
        </div>

        <div class="mb-3">
            <label for="status" class="form-label">Estatus</label>
            <select name="status" class="form-control">
                <option value="1" {{ $lounge->status ? 'selected' : '' }}>Activo</option>
                <option value="0" {{ !$lounge->status ? 'selected' : '' }}>Inactivo</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Actualizar</button>
    </form>
</div>
@endsection
