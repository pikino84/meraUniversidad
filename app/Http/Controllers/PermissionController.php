<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

/**
 * Solo accesible para Super Admin (ver routes/web.php).
 */
class PermissionController extends Controller
{
    public function index(): View
    {
        $permissions = Permission::withCount('roles')->orderBy('name')->paginate(50);

        return view('permissions.index', compact('permissions'));
    }

    public function create(): View
    {
        return view('permissions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(
            ['name' => ['required', 'string', 'max:100', 'unique:permissions,name']],
            [],
            ['name' => 'nombre del permiso']
        );

        Permission::create(['name' => trim($data['name'])]);

        return redirect()->route('permissions.index')->with('success', 'Permiso creado.');
    }

    public function edit(Permission $permission): View
    {
        return view('permissions.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $data = $request->validate(
            ['name' => ['required', 'string', 'max:100', Rule::unique('permissions', 'name')->ignore($permission->id)]],
            [],
            ['name' => 'nombre del permiso']
        );

        $permission->update(['name' => trim($data['name'])]);

        return redirect()->route('permissions.index')->with('success', 'Permiso actualizado.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        return redirect()->route('permissions.index')->with('success', "Permiso «{$permission->name}» eliminado.");
    }
}
