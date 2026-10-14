<x-guest-layout>
    <x-slot name="title">{{ $invitation ? 'Crea tu contraseña' : 'Nueva contraseña' }}</x-slot>

    <h2>{{ $invitation ? 'Crea tu contraseña' : 'Elige una nueva contraseña' }}</h2>
    <p class="mb-6 text-sm text-gray-500">Mínimo 8 caracteres, con letras y números.</p>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        @if ($invitation)
        <input type="hidden" name="invitacion" value="1">
        @endif

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autofocus autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">
            {{ $invitation ? 'Crear contraseña' : __('Reset Password') }}
        </x-primary-button>
    </form>
</x-guest-layout>
