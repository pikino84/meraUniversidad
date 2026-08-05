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

            <label>
                {{ __('Contraseña actual') }}
            </label>

            <x-text-input
                id="update_password_current_password"
                name="current_password"
                type="password"
                class="form-control mera-input"
                placeholder="Contraseña actual"
                autocomplete="current-password" />

            <x-input-error :messages="$errors->updatePassword->get('current_password')" />

        </div>



        <div class="mera-form-group">

            <label>
                {{ __('Nueva contraseña') }}
            </label>

            <x-text-input
                id="update_password_password"
                name="password"
                type="password"
                class="form-control mera-input"
                placeholder="Nueva contraseña"
                autocomplete="new-password" />

            <x-input-error :messages="$errors->updatePassword->get('password')" />

        </div>



        <div class="mera-form-group">

            <label>
                {{ __('Confirmar contraseña') }}
            </label>

            <x-text-input
                id="update_password_password_confirmation"
                name="password_confirmation"
                type="password"
                class="form-control mera-input"
                placeholder="Confirmar contraseña"
                autocomplete="new-password" />

            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" />

        </div>



        <div class="mera-form-actions">

            <button type="submit" class="btn mera-btn-save">

                <i class="fa fa-save"></i>

                Actualizar contraseña

            </button>


            @if (session('status') === 'password-updated')

            <span class="text-success ml-3">

                <i class="fa fa-check-circle"></i>

                Contraseña actualizada correctamente.

            </span>

            @endif

        </div>


    </form>

</section>