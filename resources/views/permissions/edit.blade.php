@extends('layouts.app')
@section('title', 'Editar Permiso')

@section('content')
<div class="card">
    <div class="card-header"><h5>Editar Permiso</h5></div>
    <div class="card-block">
        <form action="{{ route('permissions.update', $permission) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>Nombre del Permiso</label>
                <input type="text" name="name" class="form-control" value="{{ $permission->name }}" required>
                @error('name') <small class="text-danger">{{ $message }}</small> @enderror
            </div>

            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="{{ route('permissions.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
