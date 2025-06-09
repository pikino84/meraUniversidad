<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Crear permisos
        $permSuperAdmin = Permission::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $permEditor = Permission::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);

        // Crear roles
        $superAdminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $editorRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        // Asignar permisos a roles
        $superAdminRole->syncPermissions([$permSuperAdmin]);
        $editorRole->syncPermissions([$permEditor]);

        // Asignar rol al usuario 1
        $user = User::find(1);
        if ($user) {
            $user->assignRole($superAdminRole);
        }
    }
}
