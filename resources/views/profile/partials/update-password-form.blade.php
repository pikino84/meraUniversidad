<section>

    <div class="mera-form-group">

        <h2 class="title_deleted">
            {{ __('Actualizar contraseña') }}
        </h2>

        <p class="text-muted subtitle_deleted">
            {{ __('Asegúrate de utilizar una contraseña segura y difícil de adivinar para proteger tu cuenta.') }}
        </p>

    </div>


    <form method="post" action="{{ route('password.update') }}">

        @csrf
        @method('put')


        <div class="mera-form-group">

            <label for="update_password_current_password">
                {{ __('Contraseña actual') }}
            </label>

            <x-text-input
                id="update_password_current_password"
                name="current_password"
                type="password"
                class="form-control mera-input"
                autocomplete="current-password" />

            <x-input-error :messages="$errors->updatePassword->get('current_password')" />

        </div>



        <div class="mera-form-group">

            <label for="update_password_password">
                {{ __('Nueva contraseña') }}
            </label>

            <x-text-input
                id="update_password_password"
                name="password"
                type="password"
                class="form-control mera-input"
                placeholder="Mínimo 8 caracteres, con letras y números"
                autocomplete="new-password" />

            <x-input-error :messages="$errors->updatePassword->get('password')" />

        </div>



        <div class="mera-form-group">

            <label for="update_password_password_confirmation">
                {{ __('Confirmar contraseña') }}
            </label>

            <x-text-input
                id="update_password_password_confirmation"
                name="password_confirmation"
                type="password"
                class="form-control mera-input"
                placeholder="Escríbela de nuevo"
                autocomplete="new-password" />

            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" />

        </div>

        {{-- Auditoría UI 2026-10-14 (#15): mostrar u ocultar las contraseñas de este formulario. --}}
        <label class="mera-show-passwords">
            <input type="checkbox" class="form-check-input" data-show-passwords>
            Mostrar contraseñas
        </label>



        <div class="mera-form-actions">

            <button type="submit" class="btn mera-btn-save">

                <i class="fa fa-save" aria-hidden="true"></i>

                Actualizar contraseña

            </button>


            @if (session('status') === 'password-updated')

            <span class="text-success ml-3">

                <i class="fa fa-check-circle" aria-hidden="true"></i>

                Contraseña actualizada correctamente.

            </span>

            @endif

        </div>


    </form>

</section>