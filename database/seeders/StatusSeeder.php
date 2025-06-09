<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('status')->insert([
            ['name' => 'Activo', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Inactivo', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
