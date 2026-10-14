<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithCourseStorage;
use Tests\TestCase;

/**
 * Papelera de módulos (F2) y link para crear contraseña (U24). CLAUDE.md §12.
 */
class TrashAndInvitationsTest extends TestCase
{
    use InteractsWithCourseStorage, RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCourseStorage();
        $this->superAdmin = $this->userWithRole(User::ROLE_SUPER_ADMIN);
        $this->admin = $this->userWithRole(User::ROLE_ADMIN);
    }

    private function upload(string $name = 'Módulo de prueba'): Course
    {
        $this->actingAs($this->admin)->post(route('courses.store'), [
            'name' => $name, 'description' => 'Prueba', 'cover_image' => $this->cover(), 'zip_file' => $this->validZip(),
        ])->assertSessionHasNoErrors();

        return Course::where('name', $name)->sole();
    }

    // F2 ---------------------------------------------------------------

    public function test_a_deleted_module_can_be_restored_with_its_files_and_link(): void
    {
        $group = CourseGroup::factory()->create();
        $course = $this->upload();
        $course->update(['course_group_id' => $group->id, 'language' => 'es', 'position' => 1]);
        $index = $this->coursesRoot().'/'.basename($course->path).'/index.html';

        $this->actingAs($this->admin)->delete(route('courses.destroy', $course))->assertSessionHas('success');
        $this->assertSoftDeleted($course);
        $this->assertFileDoesNotExist($index);
        $this->getJson('/api/courses')->assertJsonMissing(['name' => 'Módulo de prueba']);

        // Solo super admin ve la Papelera.
        $this->actingAs($this->admin)->get(route('trash.index'))->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route('trash.index'))->assertOk()->assertSee('Módulo de prueba')->assertSee('Restaurar');

        $this->post(route('trash.restore', $course))->assertRedirect(route('trash.index'))->assertSessionHas('success');

        $course->refresh();
        $this->assertFalse($course->trashed());
        $this->assertNull($course->trash_items);
        $this->assertFileExists($index);
        $this->assertFileExists($this->coursesRoot().'/'.basename($course->cover_image));
        $this->assertSame($group->id, $course->course_group_id);
        $this->assertSame(1, $course->position);
    }

    public function test_a_module_whose_files_were_purged_cannot_be_restored(): void
    {
        $course = $this->upload();
        $this->actingAs($this->admin)->delete(route('courses.destroy', $course));
        File::cleanDirectory($this->sandbox.'/trash');

        $this->actingAs($this->superAdmin)->get(route('trash.index'))->assertSee('Archivos ya purgados');
        $this->post(route('trash.restore', $course))->assertSessionHas('error');
        $this->assertSoftDeleted($course);
    }

    public function test_purge_removes_old_trashed_modules_from_the_database(): void
    {
        $old = $this->upload('Viejo');
        $recent = $this->upload('Reciente');
        $this->actingAs($this->admin)->delete(route('courses.destroy', $old));
        $this->actingAs($this->admin)->delete(route('courses.destroy', $recent));
        Course::withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(31)]);

        $this->artisan('mera:purge-course-trash')->assertSuccessful();

        $this->assertNull(Course::withTrashed()->find($old->id));
        $this->assertSoftDeleted($recent);
    }

    public function test_a_new_module_can_reuse_the_name_of_one_in_the_trash(): void
    {
        $first = $this->upload('Mismo nombre');
        $this->actingAs($this->admin)->delete(route('courses.destroy', $first));

        $second = $this->upload('Mismo nombre');

        $this->assertNotSame($first->slug, $second->slug);
    }

    // U24 --------------------------------------------------------------

    public function test_new_users_receive_a_link_to_create_their_password(): void
    {
        Notification::fake();

        $this->actingAs($this->superAdmin)->get(route('users.create'))
            ->assertOk()
            ->assertDontSee('name="password"', false)
            ->assertSee('cree su contraseña', false);

        $this->post(route('users.store'), [
            'name' => 'Persona Nueva', 'email' => 'nueva@meracorporation.com', 'role' => User::ROLE_ADMIN,
            'password' => 'ignorada123', // aunque se mande, el admin no define la contraseña
        ])->assertRedirect(route('users.index'))->assertSessionHas('success');

        $user = User::where('email', 'nueva@meracorporation.com')->sole();
        $this->assertFalse(Hash::check('ignorada123', $user->password));

        $url = null;
        Notification::assertSentTo($user, AccountInvitation::class, function (AccountInvitation $notification) use ($user, &$url) {
            $url = $notification->toMail($user)->actionUrl;

            return true;
        });

        $this->assertStringContainsString('invitacion=1', $url);
        $token = basename(parse_url($url, PHP_URL_PATH));

        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Crea tu contraseña');

        // Sigue valiendo después de 60 minutos (la invitación dura 72 horas).
        $this->travel(5)->hours();
        $this->post(route('password.store'), [
            'token' => $token, 'email' => $user->email, 'invitacion' => 1,
            'password' => 'MiClave2026', 'password_confirmation' => 'MiClave2026',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('MiClave2026', $user->fresh()->password));
    }

    public function test_admin_can_resend_the_password_link_and_cannot_set_passwords(): void
    {
        Notification::fake();
        $user = $this->userWithRole(User::ROLE_ADMIN);
        $hash = $user->password;

        $this->actingAs($this->superAdmin)->put(route('users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'role' => User::ROLE_ADMIN, 'password' => 'otra12345',
        ])->assertSessionHasNoErrors();
        $this->assertSame($hash, $user->fresh()->password);

        $this->post(route('users.invite', $user))->assertSessionHas('success');
        Notification::assertSentTo($user, AccountInvitation::class);

        // Un admin no puede mandárselo a un super admin (no puede editarlo).
        $this->actingAs($this->admin)->post(route('users.invite', $this->superAdmin))->assertForbidden();
    }

    public function test_emails_use_the_mera_template_in_spanish(): void
    {
        config(['app.url' => 'https://cursos.meracorporation.com']);
        $user = new User(['name' => 'Thania', 'email' => 'thania@meracorporation.com']);

        $invitation = (string) (new AccountInvitation('token', true))->toMail($user)->render();
        $this->assertStringContainsString('src="https://cursos.meracorporation.com/images/logo-mera-university-email.png"', $invitation);
        $this->assertStringContainsString('Vision · Values · Results', $invitation);
        $this->assertStringContainsString('#024D25', $invitation); // botón con el verde MERA (tema mera.css)
        $this->assertStringNotContainsString('Laravel', $invitation);

        $reset = (new \Illuminate\Auth\Notifications\ResetPassword('token'))->toMail($user);
        $this->assertSame('Restablece tu contraseña de Mera Universidad', $reset->subject);
        $this->assertStringContainsString('Si no lo pediste tú', (string) $reset->render());
        $this->assertFileExists(public_path('images/logo-mera-university-email.png'));
    }

    public function test_a_mail_failure_keeps_the_user_and_explains_what_to_do(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

        $this->actingAs($this->superAdmin)->post(route('users.store'), [
            'name' => 'Sin correo', 'email' => 'sincorreo@meracorporation.com', 'role' => User::ROLE_ADMIN,
        ])->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['email' => 'sincorreo@meracorporation.com']);
    }
}
