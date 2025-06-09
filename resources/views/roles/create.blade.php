@extends('layouts.app')
@section('title', 'Nuevo Rol')

@section('content')
<div class="card">
    <div class="card-header"><h5>Crear Rol</h5></div>
    <div class="card-block">
        <form action="{{ route('roles.store') }}" method="POST">
            @csrf

            <div class="form-group">
                <label>Nombre del Rol</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Permisos</label>
                @foreach($permissions as $permission)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->name }}">
                        <label class="form-check-label">{{ $permission->name }}</label>
                    </div>
                @endforeach
            </div>

            <button type="submit" class="btn btn-primary">Crear</button>
            <a href="{{ route('roles.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
