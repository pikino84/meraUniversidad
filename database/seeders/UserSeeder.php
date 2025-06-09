<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        Francisco Ayapantecalth 	jfcruz@outlook.com 	super admin 	
        Jesus Armando Castro Tun 	jesus.castro@meracorporation.com 	super admin 	
        
        */
        // Crear roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super admin']);

        // Crear usuario Super Admin Francisco
        $superAdmin = User::firstOrCreate(
            ['email' => 'jfcruz@outlook.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('P4$$wOrd-2025SA'),
            ]
        );
        // crear usuario Super Admin Jesus Armando Castro Tun
        $superAdmin3 = User::firstOrCreate(
            ['email' => 'jesus.castro@meracorporation.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('P4$$wOrd-2025SA'),
            ]
        );
        ;
        // Asignar rol de super admin a los usuarios creados
        $superAdmin->assignRole($superAdminRole);
        $superAdmin3->assignRole($superAdminRole);
    }
}