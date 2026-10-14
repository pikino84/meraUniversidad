<x-guest-layout>
    <x-slot name="title">Iniciar sesión</x-slot>

    <h2>Bienvenido</h2>
    <p class="mb-6 text-sm text-gray-500">Ingresa con tu cuenta de administrador.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            {{-- U26: botón para mostrar u ocultar la contraseña. --}}
            <div class="relative mt-1" x-data="{ show: false }">
                <x-text-input id="password" class="block w-full pe-12" type="password" x-bind:type="show ? 'text' : 'password'" name="password" required autocomplete="current-password" />
                <button type="button" class="mera-password-toggle" x-on:click="show = !show"
                    x-bind:aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'" x-bind:aria-pressed="show.toString()"
                    aria-label="Mostrar contraseña" aria-pressed="false" aria-controls="password">
                    <span x-text="show ? 'Ocultar' : 'Mostrar'">Mostrar</span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-3 flex-wrap">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-mera-green shadow-sm focus:ring-mera-sky" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-mera-secondary hover:text-mera-green underline-offset-4 hover:underline" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="w-full">
            {{ __('Log in') }}
        </x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-gray-600">
        ¿Buscas los cursos? <a href="{{ route('catalog.index') }}" class="mera-auth-link">Ver catálogo</a>
    </p>
</x-guest-layout>
