<ul class="show-notification profile-notification dropdown-menu {{ $menuClass }}" data-dropdown-in="fadeIn" data-dropdown-out="fadeOut">
    <li>
        <a href="{{ route('profile.edit') }}">
            <i class="feather icon-user" aria-hidden="true"></i>
            Mi perfil
        </a>
    </li>
    <li>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <a href="{{ route('logout') }}" role="button"
                onclick="event.preventDefault(); this.closest('form').submit();">
                <i class="feather icon-log-out" aria-hidden="true"></i>
                Cerrar sesión
            </a>
        </form>
    </li>
</ul>
