<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithCourseStorage;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use InteractsWithCourseStorage, RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = $this->userWithRole(User::ROLE_SUPER_ADMIN);
        $this->admin = $this->userWithRole(User::ROLE_ADMIN);
    }

    public function test_admin_cannot_grant_super_admin_to_anyone(): void
    {
        $this->actingAs($this->admin)->put(route('users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => User::ROLE_SUPER_ADMIN,
        ])->assertSessionHasErrors('role');

        $this->assertFalse($this->admin->fresh()->isSuperAdmin());

        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Nuevo', 'email' => 'nuevo@example.com', 'password' => 'secreto123',
            'role' => User::ROLE_SUPER_ADMIN,
        ])->assertSessionHasErrors('role');
    }

    public function test_admin_cannot_edit_or_delete_a_super_admin(): void
    {
        $this->actingAs($this->admin)->get(route('users.edit', $this->superAdmin))->assertForbidden();

        $this->actingAs($this->admin)->put(route('users.update', $this->superAdmin), [
            'name' => 'Hackeado', 'email' => 'x@example.com', 'password' => 'nuevo12345', 'role' => User::ROLE_ADMIN,
        ])->assertForbidden();

        $this->actingAs($this->admin)->delete(route('users.destroy', $this->superAdmin))->assertSessionHas('error');

        $this->assertModelExists($this->superAdmin);
        $this->assertNotSame('Hackeado', $this->superAdmin->fresh()->name);
    }

    public function test_last_super_admin_cannot_be_demoted_or_deleted(): void
    {
        $this->actingAs($this->superAdmin)->put(route('users.update', $this->superAdmin), [
            'name' => $this->superAdmin->name, 'email' => $this->superAdmin->email, 'role' => User::ROLE_ADMIN,
        ])->assertSessionHasErrors('role');

        $other = $this->userWithRole(User::ROLE_SUPER_ADMIN);
        $this->actingAs($other)->delete(route('users.destroy', $this->superAdmin))->assertSessionHas('success');
        $this->actingAs($other)->delete(route('users.destroy', $other))->assertSessionHas('error');
    }

    public function test_editing_a_user_without_password_keeps_the_current_one(): void
    {
        $user = $this->userWithRole(User::ROLE_ADMIN);
        $hash = $user->password;

        $this->actingAs($this->superAdmin)->put(route('users.update', $user), [
            'name' => 'Nombre editado', 'email' => $user->email, 'password' => '', 'role' => User::ROLE_ADMIN,
        ])->assertRedirect(route('users.index'));

        $this->assertSame('Nombre editado', $user->fresh()->name);
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_roles_permissions_and_history_are_super_admin_only(): void
    {
        foreach (['roles.index', 'permissions.index', 'activity.logs.index'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertForbidden();
            $this->actingAs($this->superAdmin)->get(route($route))->assertOk();
        }
    }

    public function test_system_roles_cannot_be_deleted_or_renamed(): void
    {
        $role = Role::findByName(User::ROLE_SUPER_ADMIN);

        $this->actingAs($this->superAdmin)->delete(route('roles.destroy', $role))->assertSessionHas('error');
        $this->actingAs($this->superAdmin)->put(route('roles.update', $role), ['name' => 'otro'])->assertSessionHasErrors('name');

        $this->assertModelExists($role);
    }

    public function test_user_without_panel_role_lands_on_profile_and_cannot_see_dashboard(): void
    {
        $user = $this->userWithRole('user');

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('profile.edit'));

        $this->get(route('dashboard'))->assertForbidden();
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_api_user_endpoint_never_exposes_the_password_hash(): void
    {
        $this->assertArrayNotHasKey('password', $this->admin->toArray());
        $this->assertArrayNotHasKey('remember_token', $this->admin->toArray());
    }

    public function test_panel_pages_render_for_admin(): void
    {
        $this->setUpCourseStorage();

        foreach (['dashboard', 'courses.index', 'courses.create', 'categories.index', 'categories.create', 'users.index', 'users.create'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
    }
}
