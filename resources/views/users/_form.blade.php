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
                <label for="user-role">Rol <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="user-role" name="role" required class="form-control form-select mera-input @error('role') is-invalid @enderror">
                    <option value="" disabled @selected(! $currentRole)>Selecciona un rol</option>
                    @foreach ($roles as $role)
                    <option value="{{ $role->name }}" @selected($currentRole === $role->name)>{{ \App\Models\User::roleLabel($role->name) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- U24: el admin no define contraseñas; la persona la crea con un link por correo. --}}
    <div class="alert alert-info d-flex gap-2 align-items-start" role="note">
        <i class="fas fa-envelope mt-1" aria-hidden="true"></i>
        <div>
            @if ($editing)
            La contraseña no se cambia aquí. Para que la persona elija una nueva, usa <strong>Enviar link</strong> en la lista de usuarios: le llegará un correo válido por {{ (int) round(config('auth.passwords.invitations.expire') / 60) }} horas.
            @else
            Al guardar, enviaremos a este correo un link para que la persona <strong>cree su contraseña</strong> (válido por {{ (int) round(config('auth.passwords.invitations.expire') / 60) }} horas). Nadie más la conoce.
            @endif
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
