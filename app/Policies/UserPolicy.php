<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Reglas de gestión de usuarios:
 *  - super admin: gestiona a todos, incluido asignar "super admin".
 *  - admin: gestiona usuarios que NO son super admin y no puede otorgar "super admin".
 *  - Nadie se elimina a sí mismo ni deja el sistema sin super admins.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole(User::PANEL_ROLES);
    }

    public function create(User $actor): bool
    {
        return $actor->hasAnyRole(User::PANEL_ROLES);
    }

    public function update(User $actor, User $target): Response
    {
        if ($actor->isSuperAdmin()) {
            return Response::allow();
        }

        return $target->isSuperAdmin()
            ? Response::deny('Solo un Super Admin puede modificar a otro Super Admin.')
            : Response::allow();
    }

    public function delete(User $actor, User $target): Response
    {
        if ($actor->is($target)) {
            return Response::deny('No puedes eliminar tu propia cuenta desde aquí.');
        }

        if ($target->isSuperAdmin()) {
            if (! $actor->isSuperAdmin()) {
                return Response::deny('Solo un Super Admin puede eliminar a otro Super Admin.');
            }

            if (User::superAdminCount() <= 1) {
                return Response::deny('No se puede eliminar al último Super Admin.');
            }
        }

        return Response::allow();
    }

    /**
     * ¿Puede el actor dejar al usuario $target con el rol $role?
     */
    public function assignRole(User $actor, string $role, ?User $target = null): Response
    {
        if ($role === User::ROLE_SUPER_ADMIN && ! $actor->isSuperAdmin()) {
            return Response::deny('Solo un Super Admin puede asignar el rol Super Admin.');
        }

        $losesSuperAdmin = $target
            && $target->isSuperAdmin()
            && $role !== User::ROLE_SUPER_ADMIN
            && User::superAdminCount() <= 1;

        if ($losesSuperAdmin) {
            return Response::deny('No se puede quitar el rol al último Super Admin.');
        }

        return Response::allow();
    }
}
