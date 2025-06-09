@extends('layouts.app')
@section('title', 'Editar Usuario')

@section('content')
<div class="card">
    <div class="card-header"><h5>Editar Usuario</h5></div>
    <div class="card-block">
        <form action="{{ route('users.update', $user) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>Nombre</label>
                <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
            </div>

            <div class="form-group">
                <label>Correo Electrónico</label>
                <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
            </div>
            <div class="form-group">
                <label>Nueva Contraseña (opcional)</label>
                <input type="password" name="password" class="form-control">
            </div>
            <div class="form-group">
                <label>Roles</label>                
                <select name="role" class="form-control" required>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" {{ $user->roles->contains('name', $role->name) ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>                
            </div>
            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
