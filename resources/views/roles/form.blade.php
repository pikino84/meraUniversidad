@csrf

<div class="form-group mera-form-group">

    <label>
        Nombre del Rol
    </label>

    <input
        type="text"
        name="name"
        id="name"
        class="form-control mera-input"
        value="{{ old('name', $role->name ?? '') }}"
        placeholder="Ingrese el nombre del rol"
        required>

    @error('name')
    <small class="text-danger">{{ $message }}</small>
    @enderror

</div>

<div class="mera-form-actions">

    <button
        type="submit"
        class="btn mera-btn-save">

        <i class="fas fa-save"></i>

        {{ $buttonText }}

    </button>

    <a
        href="{{ route('roles.index') }}"
        class="btn mera-btn-cancel">

        <i class="fas fa-times"></i>

        Cancelar

    </a>

</div>