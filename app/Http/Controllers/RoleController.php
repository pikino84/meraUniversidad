<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Solo accesible para Super Admin (ver routes/web.php).
 */
class RoleController extends Controller
{
    /** Roles de los que depende la app: no se pueden renombrar ni eliminar. */
    public const SYSTEM_ROLES = User::PANEL_ROLES;

    public function index(): View
    {
        $roles = Role::with('permissions:id,name')->withCount('users')->orderBy('name')->get();

        return view('roles.index', ['roles' => $roles, 'systemRoles' => self::SYSTEM_ROLES]);
    }

    public function create(): View
    {
        return view('roles.create', ['permissions' => Permission::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [], ['name' => 'nombre del rol', 'permissions' => 'permisos']);

        DB::transaction(function () use ($data) {
            $role = Role::create(['name' => trim($data['name'])]);
            $role->syncPermissions($data['permissions'] ?? []);
        });

        return redirect()->route('roles.index')->with('success', 'Rol creado con éxito.');
    }

    public function edit(Role $role): View
    {
        return view('roles.edit', [
            'role' => $role->load('permissions:id,name'),
            'permissions' => Permission::orderBy('name')->get(),
            'isSystemRole' => in_array($role->name, self::SYSTEM_ROLES, true),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $isSystemRole = in_array($role->name, self::SYSTEM_ROLES, true);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->ignore($role->id),
                function ($attribute, $value, $fail) use ($isSystemRole, $role) {
                    if ($isSystemRole && $value !== $role->name) {
                        $fail('Los roles del sistema no se pueden renombrar.');
                    }
                },
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [], ['name' => 'nombre del rol', 'permissions' => 'permisos']);

        DB::transaction(function () use ($role, $data) {
            $role->update(['name' => trim($data['name'])]);
            $role->syncPermissions($data['permissions'] ?? []);
        });

        return redirect()->route('roles.index')->with('success', 'Rol actualizado.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            return redirect()->route('roles.index')->with('error', 'Los roles del sistema no se pueden eliminar.');
        }

        if ($role->users()->exists()) {
            return redirect()->route('roles.index')->with('error', 'No se puede eliminar un rol asignado a usuarios. Reasígnalos primero.');
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', "Rol «{$role->name}» eliminado.");
    }
}
