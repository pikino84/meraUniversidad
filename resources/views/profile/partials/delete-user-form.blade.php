<section>
    <div class="mera-form-group">
        <h2 class="title_deleted">
            {{ __('Eliminar Cuenta') }}
        </h2>

        <p class="text-muted subtitle_deleted">
            {{ __('Una vez eliminada tu cuenta, todos sus recursos y datos serán eliminados permanentemente. Antes de eliminarla, descarga cualquier información que desees conservar.') }}
        </p>
    </div>

    <button type="button" class="btn mera-btn-cancel" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
        <i class="fa fa-trash"></i>
        Eliminar Cuenta
    </button>

    <x-modal class="modal_custom" name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>

        <form method="post" action="{{ route('profile.destroy') }}" class="p-4">
            @csrf
            @method('delete')

            <div class="mera-form-group">
                <h2 class="title_deleted">
                    {{ __('¿Estás seguro de eliminar tu cuenta?') }}
                </h2>

                <p class="text-muted subtitle_deleted">
                    {{ __('Esta acción no puede deshacerse. Ingresa tu contraseña para confirmar la eliminación permanente de tu cuenta.') }}
                </p>
            </div>

            <div class="mera-form-group">
                <label>
                    {{ __('Contraseña') }}
                </label>

                <x-text-input id="password" name="password" type="password" class="form-control mera-input" placeholder="Contraseña" />

                <x-input-error :messages="$errors->userDeletion->get('password')" />
            </div>

            <div class="mera-form-actions">
                <button type="button" class="btn mera-btn-cancel" x-on:click="$dispatch('close')">
                    <i class="fa fa-times"></i>
                    Cancelar
                </button>

                <button class="btn mera-btn-save" style="background:#dc3545;color:white;">
                    <i class="fa fa-trash"></i>
                    Eliminar Cuenta
                </button>
            </div>
        </form>
    </x-modal>
</section>