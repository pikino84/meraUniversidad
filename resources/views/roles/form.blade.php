@csrf

<div class="form-group">
    <label for="name">Nombre del Rol</label>
    <input type="text" name="name" id="name" class="form-control" 
           value="{{ old('name', $role->name ?? '') }}" required>
    @error('name') <small class="text-danger">{{ $message }}</small> @enderror
</div>

<button type="submit" class="btn btn-primary">{{ $buttonText }}</button>
<a href="{{ route('roles.index') }}" class="btn btn-secondary">Cancelar</a>
