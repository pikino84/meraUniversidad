{{-- Variables: $action, $method, $permission (nullable), $submitLabel --}}
@include('partials.form-errors')

<form action="{{ $action }}" method="POST">
    @csrf
    @if ($method !== 'POST')
    @method($method)
    @endif

    <div class="form-group mera-form-group mb-3">
        <label for="permission-name">Nombre del permiso <span class="text-danger" aria-hidden="true">*</span></label>
        <input id="permission-name" type="text" name="name" maxlength="100" required
            class="form-control mera-input @error('name') is-invalid @enderror"
            value="{{ old('name', $permission->name ?? '') }}" placeholder="Ej. cursos.publicar">
    </div>

    <div class="mera-form-actions">
        <button type="submit" class="btn mera-btn-save">
            <i class="fas fa-save" aria-hidden="true"></i> {{ $submitLabel }}
        </button>
        <a href="{{ route('permissions.index') }}" class="btn mera-btn-cancel">
            <i class="fas fa-times" aria-hidden="true"></i> Cancelar
        </a>
    </div>
</form>
