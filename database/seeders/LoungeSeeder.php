<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lounge;

class LoungeSeeder extends Seeder
{
    public function run(): void
    {
        $lounges = [
            [
                'name' => 'VIP Lounge Terminal 1',
                'terminal' => 'Terminal 1',
                'capacity' => 100,
                'status' => true,
            ],
            [
                'name' => 'Business Lounge Terminal 2',
                'terminal' => 'Terminal 2',
                'capacity' => 80,
                'status' => true,
            ],
            [
                'name' => 'First Class Lounge Terminal 3',
                'terminal' => 'Terminal 3',
                'capacity' => 50,
                'status' => true,
            ],
            [
                'name' => 'International Lounge Terminal 4',
                'terminal' => 'Terminal 4',
                'capacity' => 120,
                'status' => true,
            ],
        ];

        foreach ($lounges as $lounge) {
            Lounge::create($lounge);
        }
    }
}
