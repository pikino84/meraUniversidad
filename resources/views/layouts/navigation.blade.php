@php
    $isPanelUser = auth()->user()->hasAnyRole(\App\Models\User::PANEL_ROLES);
    $isSuperAdmin = auth()->user()->isSuperAdmin();

    // Secciones del menú: [título, [[ruta, patrón activo, icono, texto, visible], …]]
    $sections = [
        ['Contenido', [
            ['dashboard', 'dashboard', 'fas fa-tachometer-alt', 'Dashboard', $isPanelUser],
            ['courses.index', 'courses.*', 'fas fa-book', 'Módulos', $isPanelUser],
            ['course-groups.index', 'course-groups.*', 'fas fa-folder', 'Carpetas de curso', $isPanelUser],
            ['progress.index', 'progress.*', 'fas fa-chart-line', 'Seguimiento anual', $isPanelUser],
            ['markets.index', 'markets.*', 'fas fa-globe', 'Versiones', $isSuperAdmin],
            ['course-catalog.index', 'course-catalog.*', 'fas fa-list-alt', 'Catálogo de cursos', $isSuperAdmin],
            ['categories.index', 'categories.*', 'fas fa-tags', 'Categorías', $isPanelUser && config('mera.categories_enabled')],
        ]],
        ['Administración', [
            ['users.index', 'users.*', 'fas fa-users', 'Usuarios', $isPanelUser],
            ['roles.index', 'roles.*', 'fas fa-id-badge', 'Roles', $isSuperAdmin],
            ['permissions.index', 'permissions.*', 'fas fa-key', 'Permisos', $isSuperAdmin],
            ['activity.logs.index', 'activity.logs.*', 'fas fa-history', 'Historial', $isSuperAdmin],
            ['trash.index', 'trash.*', 'fas fa-trash-alt', 'Papelera', $isSuperAdmin],
        ]],
        // U20: visible para todos, así el menú conserva el elemento activo al entrar a Mi perfil.
        ['Cuenta', [
            ['profile.edit', 'profile.*', 'fas fa-user', 'Mi perfil', true],
        ]],
    ];
@endphp
{{-- Menú lateral: lista plana por secciones (antes era un acordeón "Administración" que, al
     pulsarlo, ocultaba todas las opciones). Auditoría UI 2026-10-14. --}}
<nav class="pcoded-navbar mera-sidebar" aria-label="Menú principal">
    <div class="nav-list">
        <div class="pcoded-inner-navbar main-menu">
            @foreach ($sections as [$heading, $items])
            @php $items = array_filter($items, fn ($item) => $item[4]); @endphp
            @continue($items === [])
            <div class="pcoded-navigatio-lavel mera-nav-label">{{ $heading }}</div>
            <ul class="pcoded-item pcoded-left-item">
                @foreach ($items as [$route, $pattern, $icon, $label])
                @php $active = request()->routeIs($pattern); @endphp
                <li class="{{ $active ? 'active' : '' }}">
                    <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif>
                        <span class="pcoded-micon"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                        <span class="pcoded-mtext">{{ $label }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
            @endforeach
        </div>
    </div>
</nav>
