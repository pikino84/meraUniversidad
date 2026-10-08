{{-- Variables: $action, $method, $role (nullable), $permissions, $isSystemRole, $submitLabel --}}
@php
    $assigned = collect(old('permissions', isset($role) ? $role->permissions->pluck('name')->all() : []));
@endphp
@include('partials.form-errors')

<form action="{{ $action }}" method="POST">
    @csrf
    @if ($method !== 'POST')
    @method($method)
    @endif

    <div class="form-group mera-form-group mb-3">
        <label for="role-name">Nombre del rol <span class="text-danger" aria-hidden="true">*</span></label>
        <input id="role-name" type="text" name="name" maxlength="100" required
            class="form-control mera-input @error('name') is-invalid @enderror"
            value="{{ old('name', $role->name ?? '') }}" placeholder="Ej. editor"
            @if ($isSystemRole ?? false) readonly aria-describedby="role-name-help" @endif>
        @if ($isSystemRole ?? false)
        <small id="role-name-help" class="form-text text-muted">
            <i class="fa fa-lock" aria-hidden="true"></i> Rol del sistema: el nombre no se puede cambiar.
        </small>
        @endif
    </div>

    <fieldset class="form-group mera-form-group mb-3">
        <legend class="fs-6 fw-semibold">Permisos</legend>
        @if ($permissions->isEmpty())
        <p class="text-muted mb-0">No hay permisos registrados.</p>
        @else
        <div class="row">
            @foreach ($permissions as $permission)
            <div class="col-md-4 col-sm-6 mb-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="permission{{ $permission->id }}"
                        name="permissions[]" value="{{ $permission->name }}" @checked($assigned->contains($permission->name))>
                    <label class="form-check-label" for="permission{{ $permission->id }}">{{ $permission->name }}</label>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </fieldset>

    <div class="mera-form-actions">
        <button type="submit" class="btn mera-btn-save">
            <i class="fas fa-save" aria-hidden="true"></i> {{ $submitLabel }}
        </button>
        <a href="{{ route('roles.index') }}" class="btn mera-btn-cancel">
            <i class="fas fa-times" aria-hidden="true"></i> Cancelar
        </a>
    </div>
</form>
