@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Crear Sala</h1>

    <form method="POST" action="{{ route('lounges.store') }}">
        @csrf

        <div class="mb-3">
            <label for="name" class="form-label">Nombre de la Sala</label>
            <input type="text" name="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="terminal" class="form-label">Terminal</label>
            <input type="text" name="terminal" class="form-control">
        </div>

        <div class="mb-3">
            <label for="capacity" class="form-label">Capacidad</label>
            <input type="number" name="capacity" class="form-control">
        </div>

        <div class="mb-3">
            <label for="status" class="form-label">Estatus</label>
            <select name="status" class="form-control">
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
        </div>

        <button type="submit" class="btn btn-success">Guardar</button>
    </form>
</div>
@endsection
