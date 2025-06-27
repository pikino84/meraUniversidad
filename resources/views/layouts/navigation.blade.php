<nav class="pcoded-navbar">
    <div class="nav-list">
        <div class="pcoded-inner-navbar main-menu">
            <div class="pcoded-navigation-label">Navigation</div>
            <ul class="pcoded-item pcoded-left-item">
                <li class="pcoded-hasmenu {{ menuActive(['dashboard', 'users.*', 'permissions.*', 'roles.*', 'activity.logs.*', 'lounges.*']) }}">
                    <a href="javascript:void(0)" class="waves-effect waves-dark">
                        <span class="pcoded-micon"><i class="feather icon-sidebar"></i></span>
                        <span class="pcoded-mtext">Panel de Administración</span>
                    </a>
                    <ul class="pcoded-submenu">
                        <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <a href="{{ route('dashboard') }}" class="waves-effect waves-dark">
                                <span class="pcoded-mtext">Dashboard</span>
                            </a>
                        </li>
                        <li class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <a href="{{ route('users.index') }}" class="waves-effect waves-dark">
                                <span class="pcoded-mtext">Usuarios</span>
                            </a>
                        </li>
                        <li class="{{ request()->routeIs('permissions.*') ? 'active' : '' }}">
                            <a href="{{ route('permissions.index') }}" class="waves-effect waves-dark">
                                <span class="pcoded-mtext">Permisos</span>
                            </a>
                        </li>
                        <li class="{{ request()->routeIs('roles.*') ? 'active' : '' }}">
                            <a href="{{ route('roles.index') }}" class="waves-effect waves-dark">
                                <span class="pcoded-mtext">Roles</span>
                            </a>
                        </li>                        
                        <li class="{{ request()->routeIs('activity.logs.*') ? 'active' : '' }}">
                            <a href="{{ route('activity.logs.index') }}" class="waves-effect waves-dark">
                                <span class="pcoded-mtext">Historial de Actividad</span>
                            </a>
                        </li>
                        <!-- <li class="{{ request()->routeIs('lounges.*') ? 'active' : '' }}">
                            <a href="{{ route('lounges.index') }}" class="waves-effect waves-dark">
                                <span class="pcoded-mtext">Salas Lounge</span>
                            </a>
                        </li> -->
                        <li class="{{ request()->routeIs('courses.*') ? 'active' : '' }}">
                            <a href="{{ route('courses.index') }}" class="waves-effect waves-dark">
                                <span class="pcoded-mtext">Cursos</span>
                            </a>
                        </li>
                    </ul>
                </li>               
            </ul>
        </div>
    </div>
</nav>
