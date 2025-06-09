@extends('layouts.app')
@section('title', 'Crear Permiso')

@section('content')
<div class="card">
    <div class="card-header"><h5>Crear Permiso</h5></div>
    <div class="card-block">
        <form action="{{ route('permissions.store') }}" method="POST">
            @csrf

            <div class="form-group">
                <label>Nombre del Permiso</label>
                <input type="text" name="name" class="form-control" required>
                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('permissions.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection