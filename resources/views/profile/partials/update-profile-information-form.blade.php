<section>

    <div class="mera-form-group">

        <h2 class="title_deleted">
            {{ __('Información del perfil') }}
        </h2>

        <p class="text-muted subtitle_deleted">
            {{ __('Actualiza la información de tu perfil y dirección de correo electrónico.') }}
        </p>

    </div>


    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>


    <form method="post" action="{{ route('profile.update') }}">

        @csrf
        @method('patch')


        <div class="mera-form-group">

            <label>
                {{ __('Nombre') }}
            </label>

            <x-text-input
                id="name"
                name="name"
                type="text"
                class="form-control mera-input"
                :value="old('name', $user->name)"
                required
                autofocus
                autocomplete="name" />

            <x-input-error :messages="$errors->get('name')" />

        </div>



        <div class="mera-form-group">

            <label>
                {{ __('Correo electrónico') }}
            </label>

            <x-text-input
                id="email"
                name="email"
                type="email"
                class="form-control mera-input"
                :value="old('email', $user->email)"
                required
                autocomplete="username" />

            <x-input-error :messages="$errors->get('email')" />



            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())

            <div class="alert alert-warning mt-3">

                <p class="mb-2">

                    <i class="fa fa-exclamation-triangle"></i>

                    {{ __('Tu correo electrónico aún no ha sido verificado.') }}

                </p>


                <button
                    form="send-verification"
                    class="btn btn-sm mera-btn-save">

                    <i class="fa fa-envelope"></i>

                    {{ __('Enviar correo de verificación nuevamente') }}

                </button>


                @if (session('status') === 'verification-link-sent')

                <p class="text-success mt-2 mb-0">

                    <i class="fa fa-check-circle"></i>

                    {{ __('Se ha enviado un nuevo enlace de verificación a tu correo.') }}

                </p>

                @endif


            </div>

            @endif


        </div>



        <div class="mera-form-actions">


            <button type="submit" class="btn mera-btn-save">

                <i class="fa fa-save"></i>

                Guardar cambios

            </button>



            @if (session('status') === 'profile-updated')

            <span class="text-success ml-3">

                <i class="fa fa-check-circle"></i>

                {{ __('Guardado correctamente.') }}

            </span>

            @endif


        </div>


    </form>


</section>