<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RotatePasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rotates_super_admin_passwords_and_resets_remember_tokens(): void
    {
        Role::findOrCreate(User::ROLE_SUPER_ADMIN, 'web');
        $admin = User::factory()->create()->assignRole(User::ROLE_SUPER_ADMIN);
        $other = User::factory()->create();
        $oldToken = $admin->remember_token;

        $this->artisan('mera:rotate-password', ['--super-admins' => true])
            ->expectsConfirmation('¿Rotar la contraseña de 1 usuario(s)?', 'yes')
            ->assertSuccessful();

        $this->assertFalse(Hash::check('password', $admin->fresh()->password));
        $this->assertNotSame($oldToken, $admin->fresh()->remember_token);
        $this->assertTrue(Hash::check('password', $other->fresh()->password));
    }
}
