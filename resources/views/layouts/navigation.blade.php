@php
    $isPanelUser = auth()->user()->hasAnyRole(\App\Models\User::PANEL_ROLES);
    $isSuperAdmin = auth()->user()->isSuperAdmin();

    // [ruta, patrón activo, icono, texto, visible]
    $items = [
        ['dashboard', 'dashboard', 'fas fa-tachometer-alt', 'Dashboard', $isPanelUser],
        ['courses.index', 'courses.*', 'fas fa-book', 'Cursos', $isPanelUser],
        ['categories.index', 'categories.*', 'fas fa-tags', 'Categorías', $isPanelUser],
        ['users.index', 'users.*', 'fas fa-users', 'Usuarios', $isPanelUser],
        ['roles.index', 'roles.*', 'fas fa-id-badge', 'Roles', $isSuperAdmin],
        ['permissions.index', 'permissions.*', 'fas fa-key', 'Permisos', $isSuperAdmin],
        ['activity.logs.index', 'activity.logs.*', 'fas fa-history', 'Historial', $isSuperAdmin],
        ['profile.edit', 'profile.*', 'fas fa-user', 'Mi perfil', ! $isPanelUser],
    ];

    $items = array_filter($items, fn ($item) => $item[4]);
@endphp
<nav class="pcoded-navbar" aria-label="Menú principal">
    <div class="nav-list">
        <div class="pcoded-inner-navbar main-menu">
            <ul class="pcoded-item pcoded-left-item">
                <li class="pcoded-hasmenu {{ menuActive(array_column($items, 1)) }}">
                    <a href="javascript:void(0)" class="waves-effect waves-dark">
                        <span class="pcoded-micon"><i class="feather icon-sidebar" aria-hidden="true"></i></span>
                        <span class="pcoded-mtext">Administración</span>
                    </a>
                    <ul class="pcoded-submenu">
                        @foreach ($items as [$route, $pattern, $icon, $label])
                        <li class="{{ request()->routeIs($pattern) ? 'active' : '' }}">
                            <a href="{{ route($route) }}" class="waves-effect waves-dark"
                                @if (request()->routeIs($pattern)) aria-current="page" @endif>
                                <span class="pcoded-micon"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                                <span class="pcoded-mtext">{{ $label }}</span>
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
