{{-- Variables: $action, $method, $user (nullable), $roles, $submitLabel --}}
@php
    $editing = isset($user);
    $currentRole = old('role', $editing ? $user->roles->first()?->name : null);
@endphp
@include('partials.form-errors')

<form action="{{ $action }}" method="POST" autocomplete="off">
    @csrf
    @if ($method !== 'POST')
    @method($method)
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mera-form-group mb-3">
                <label for="user-name">Nombre <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="user-name" type="text" name="name" maxlength="255" required
                    class="form-control mera-input @error('name') is-invalid @enderror"
                    value="{{ old('name', $user->name ?? '') }}" placeholder="Nombre completo">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group mera-form-group mb-3">
                <label for="user-email">Correo electrónico <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="user-email" type="email" name="email" maxlength="255" required
                    class="form-control mera-input @error('email') is-invalid @enderror"
                    value="{{ old('email', $user->email ?? '') }}" placeholder="nombre@empresa.com">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group mera-form-group mb-3">
                <label for="user-password">
                    {{ $editing ? 'Nueva contraseña' : 'Contraseña' }}
                    @if ($editing)
                    <small class="text-muted">(opcional)</small>
                    @else
                    <span class="text-danger" aria-hidden="true">*</span>
                    @endif
                </label>
                <input id="user-password" type="password" name="password" autocomplete="new-password"
                    class="form-control mera-input @error('password') is-invalid @enderror"
                    @unless ($editing) required @endunless
                    aria-describedby="user-password-help">
                <small id="user-password-help" class="form-text text-muted">
                    Mínimo 8 caracteres, con letras y números.
                    @if ($editing) Déjala vacía para conservar la actual. @endif
                </small>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group mera-form-group mb-3">
                <label for="user-role">Rol <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="user-role" name="role" required class="form-control form-select mera-input @error('role') is-invalid @enderror">
                    <option value="" disabled @selected(! $currentRole)>Selecciona un rol</option>
                    @foreach ($roles as $role)
                    <option value="{{ $role->name }}" @selected($currentRole === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="mera-form-actions">
        <button type="submit" class="btn mera-btn-save">
            <i class="fas fa-save" aria-hidden="true"></i> {{ $submitLabel }}
        </button>
        <a href="{{ route('users.index') }}" class="btn mera-btn-cancel">
            <i class="fas fa-times" aria-hidden="true"></i> Cancelar
        </a>
    </div>
</form>
