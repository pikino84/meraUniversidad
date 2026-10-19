<?php

namespace Tests\Feature;

use App\Console\Commands\VersionsFromLanguages;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseGroupVersion;
use App\Models\CourseReport;
use App\Models\Market;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Versiones por país, "+ Año" y migración de lo existente (docs/carpetas/INSTRUCTIVO_VERSIONES.md).
 */
class CourseVersionsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = $this->userWithRole(User::ROLE_SUPER_ADMIN);
        $this->admin = $this->userWithRole(User::ROLE_ADMIN);
    }

    private function userWithRole(string $role): User
    {
        \Spatie\Permission\Models\Role::findOrCreate($role, 'web');

        return tap(User::factory()->create())->assignRole($role);
    }

    private function market(string $code): Market
    {
        return Market::where('code', $code)->firstOrFail();
    }

    /* ==================== Pantalla "Versiones" ==================== */

    public function test_only_super_admins_manage_markets(): void
    {
        $this->actingAs($this->admin)->get(route('markets.index'))->assertForbidden();
        $this->actingAs($this->superAdmin)->get(route('markets.index'))->assertOk()->assertSee('México')->assertSee('Latam')->assertSee('EUA');
    }

    public function test_a_new_market_can_belong_to_another_and_its_link_must_be_free(): void
    {
        $latam = $this->market('LATAM');
        CourseGroup::create(['name' => 'Higiene', 'slug' => 'higiene']);

        $this->actingAs($this->superAdmin)->post(route('markets.store'), [
            'name' => 'Panamá', 'code' => 'pa', 'slug' => 'panama', 'language' => 'es', 'hreflang' => 'es-PA',
            'parent_id' => $latam->id, 'is_active' => 1,
        ])->assertRedirect(route('markets.index'));

        $panama = Market::where('code', 'PA')->firstOrFail();
        $this->assertSame([$latam->id, 4, true], [$panama->parent_id, $panama->position, $panama->is_active]);

        // El link no puede ser el de una carpeta, una ruta ni "en"; ni depender de una versión hija.
        foreach (['higiene', 'login', 'en', 'Con Espacio'] as $slug) {
            $this->actingAs($this->superAdmin)->post(route('markets.store'), [
                'name' => 'X'.$slug, 'code' => 'X'.Str::upper(Str::random(3)), 'slug' => $slug, 'language' => 'es', 'is_active' => 1,
            ])->assertSessionHasErrors('slug');
        }

        $this->actingAs($this->superAdmin)->post(route('markets.store'), [
            'name' => 'Colombia', 'code' => 'CO', 'slug' => 'colombia', 'language' => 'es', 'parent_id' => $panama->id, 'is_active' => 1,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_a_used_market_cannot_be_deleted_only_deactivated(): void
    {
        $mx = $this->market('MX');
        CourseGroup::create(['name' => 'Higiene'])->versions()->create(['year' => 2026, 'market_id' => $mx->id]);

        $this->actingAs($this->superAdmin)->delete(route('markets.destroy', $mx))->assertSessionHas('error');
        $this->assertModelExists($mx);

        $this->actingAs($this->superAdmin)->put(route('markets.update', $mx), [
            'name' => 'México', 'code' => 'MX', 'slug' => 'mexico', 'language' => 'es', 'is_active' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertFalse($mx->fresh()->is_active);

        $unused = Market::create(['code' => 'PA', 'name' => 'Panamá', 'slug' => 'panama']);
        $this->actingAs($this->superAdmin)->delete(route('markets.destroy', $unused))->assertSessionHas('success');
        $this->assertModelMissing($unused);
    }

    public function test_markets_can_be_reordered(): void
    {
        $this->actingAs($this->superAdmin)->patch(route('markets.move', $this->market('US')), ['direction' => 'up']);

        $this->assertSame(['MX', 'US', 'LATAM'], Market::ordered()->pluck('code')->all());
    }

    /* ==================== "+ Año" y versiones ==================== */

    public function test_new_year_copies_only_the_structure(): void
    {
        $group = CourseGroup::create(['name' => 'Higiene']);
        $mx = $group->versions()->create([
            'year' => 2026, 'market_id' => $this->market('MX')->id, 'catalog_code' => 'NRM-HIG-002-EL-26',
            'description' => 'Texto', 'evaluation_url' => 'https://forms.cloud.microsoft/r/EVAL',
        ]);
        $mx->forceFill(['is_published' => true])->save();
        $group->versions()->create(['year' => 2026, 'market_id' => $this->market('US')->id, 'title' => 'Hygiene']);
        Course::factory()->create(['course_group_id' => $group->id, 'course_group_version_id' => $mx->id, 'language' => 'es', 'position' => 1]);

        $this->actingAs($this->admin)->post(route('course-groups.versions.store', $group), ['year' => 2027, 'copy_from' => 2026])
            ->assertSessionHas('success');

        $new = $group->versions()->where('year', 2027)->with('market')->get()->keyBy(fn ($v) => $v->market->code);
        $this->assertSame(['MX', 'US'], $new->keys()->sort()->values()->all());
        $this->assertSame(['Texto', 'https://forms.cloud.microsoft/r/EVAL', null, false, 0],
            [$new['MX']->description, $new['MX']->evaluation_url, $new['MX']->catalog_code, $new['MX']->is_published, $new['MX']->modules()->count()]);
        $this->assertSame('Hygiene', $new['US']->title);

        // Repetirlo no duplica nada.
        $this->actingAs($this->admin)->post(route('course-groups.versions.store', $group), ['year' => 2027, 'copy_from' => 2026])
            ->assertSessionHas('error');
        $this->assertSame(4, $group->versions()->count());
    }

    public function test_new_year_or_version_with_chosen_markets(): void
    {
        $group = CourseGroup::create(['name' => 'Higiene']);

        $this->actingAs($this->admin)->post(route('course-groups.versions.store', $group), ['year' => 2026])
            ->assertSessionHasErrors('market_ids');

        $this->actingAs($this->admin)->post(route('course-groups.versions.store', $group), [
            'year' => 2026, 'market_ids' => [$this->market('MX')->id, $this->market('LATAM')->id],
        ])->assertSessionHas('success');

        $this->assertSame(2, $group->versions()->where('year', 2026)->count());
    }

    public function test_only_an_empty_version_can_be_deleted(): void
    {
        $group = CourseGroup::create(['name' => 'Higiene']);
        $full = $group->versions()->create(['year' => 2026, 'market_id' => $this->market('MX')->id]);
        $empty = $group->versions()->create(['year' => 2026, 'market_id' => $this->market('LATAM')->id]);
        Course::factory()->create(['course_group_id' => $group->id, 'course_group_version_id' => $full->id, 'language' => 'es', 'position' => 1]);

        $this->actingAs($this->admin)->delete(route('course-groups.versions.destroy', [$group, $full]))->assertSessionHas('error');
        $this->actingAs($this->admin)->delete(route('course-groups.versions.destroy', [$group, $empty]))->assertSessionHas('success');

        $this->assertModelExists($full);
        $this->assertModelMissing($empty);
    }

    public function test_a_version_of_another_folder_is_not_found(): void
    {
        $group = CourseGroup::create(['name' => 'Higiene']);
        $other = CourseGroup::create(['name' => 'Otra'])->versions()->create(['year' => 2026, 'market_id' => $this->market('MX')->id]);

        $this->actingAs($this->admin)->get(route('course-groups.versions.edit', [$group, $other]))->assertNotFound();
    }

    /* ==================== Migración de lo existente ==================== */

    /** Estado previo a las versiones: carpeta + idioma, con los datos de la página en la carpeta. */
    private function legacy(): array
    {
        $higiene = CourseGroup::create(['name' => 'NRM-HIG-002-EL-26 | Introducción a la Higiene 2026', 'slug' => 'introduccion-a-la-higiene']);
        $higiene->forceFill([
            'name_en' => 'Introduction to Hygiene', 'description_es' => 'Español', 'description_en' => 'English',
            'evaluation_url_es' => 'https://forms.cloud.microsoft/r/ES', 'is_published' => true, 'published_at' => now(),
        ])->save();
        $vip = CourseGroup::create(['name' => 'NEG-OPE-016-EL-26-MX | Salas VIP', 'slug' => 'salas-vip']);
        $acoso = CourseGroup::create(['name' => 'NRM-SEG-003-EL-25 | Hostigamiento 2025', 'slug' => 'hostigamiento-25']);
        $alimentos = CourseGroup::create(['name' => 'Manipulación de alimentos', 'slug' => 'manipulacion-de-alimentos']);

        $mod = fn ($group, $language, $position) => Course::factory()->create(['course_group_id' => $group->id, 'language' => $language, 'position' => $position]);

        return [
            'groups' => compact('higiene', 'vip', 'acoso', 'alimentos'),
            'courses' => [
                'h_es2' => $mod($higiene, 'es', 2), 'h_es1' => $mod($higiene, 'es', 1), 'h_en1' => $mod($higiene, 'en', 1),
                'vip' => $mod($vip, 'es', 1), 'acoso' => $mod($acoso, 'es', 1), 'alimentos' => $mod($alimentos, 'es', 1),
                'suelto' => Course::factory()->create(),
            ],
            'report' => CourseReport::forceCreate([
                'course_group_id' => $higiene->id, 'title' => 'Corte', 'cut_date' => '2026-10-13', 'survey' => 'higiene', 'data' => [],
            ]),
        ];
    }

    public function test_catalog_codes_are_parsed_from_folder_names(): void
    {
        $this->assertSame(['NRM-HIG-002-EL-26', 2026, null], VersionsFromLanguages::parseCode('NRM-HIG-002-EL-26 | Higiene'));
        $this->assertSame(['NRM-SEG-005-EL-26-MX', 2026, 'MX'], VersionsFromLanguages::parseCode('NRM-SEG-005-EL-26-MX | Factores'));
        $this->assertSame([null, null, null], VersionsFromLanguages::parseCode('Manipulación de alimentos'));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->legacy();

        $this->artisan('mera:versions-from-languages', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, CourseGroupVersion::count());
        $this->assertSame(0, Course::whereNotNull('course_group_version_id')->count());
    }

    public function test_command_moves_languages_to_versions_by_year_and_country(): void
    {
        ['groups' => $g, 'courses' => $c, 'report' => $report] = $this->legacy();
        $backupDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mera-versions-'.Str::random(6);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($backupDir));

        $run = fn () => $this->artisan('mera:versions-from-languages', [
            '--map' => ['manipulacion-de-alimentos:LATAM'], '--year' => 2026, '--backup-dir' => $backupDir,
        ])->assertSuccessful();
        $run();

        $version = fn ($group, $code, $year) => $group->versions()->where('year', $year)->where('market_id', $this->market($code)->id)->first();

        // Higiene 2026: español → México, inglés → EUA, con los datos de la carpeta y su publicación.
        $hMx = $version($g['higiene'], 'MX', 2026);
        $hUs = $version($g['higiene'], 'US', 2026);
        $this->assertSame(['NRM-HIG-002-EL-26', 'Español', 'https://forms.cloud.microsoft/r/ES', true, null],
            [$hMx->catalog_code, $hMx->description, $hMx->evaluation_url, $hMx->is_published, $hMx->title]);
        $this->assertSame(['Introduction to Hygiene', 'English', 'https://forms.cloud.microsoft/r/ES'], [$hUs->title, $hUs->description, $hUs->evaluation_url]);
        $this->assertSame([$c['h_es1']->id, $c['h_es2']->id], $hMx->modules()->ordered()->pluck('id')->all()); // conserva el orden
        $this->assertSame([$hUs->id, 'en'], [$c['h_en1']->fresh()->course_group_version_id, $c['h_en1']->fresh()->language]);

        // Sufijo -MX → México; año del código (-25 → 2025); --map → Latam.
        $this->assertNotNull($version($g['vip'], 'MX', 2026));
        $this->assertNotNull($version($g['acoso'], 'MX', 2025));
        $this->assertNotNull($version($g['alimentos'], 'LATAM', 2026));

        // Los módulos sueltos no se tocan; el corte pasa a la versión en español de su año.
        $this->assertNull($c['suelto']->fresh()->course_group_id);
        $this->assertSame($hMx->id, $report->fresh()->course_group_version_id);

        // Respaldo JSON e idempotencia.
        $this->assertCount(1, File::files($backupDir));
        $run();
        $this->assertSame(5, CourseGroupVersion::count());
    }

    public function test_the_old_page_url_keeps_working_after_migration(): void
    {
        ['groups' => $g] = $this->legacy();
        $this->artisan('mera:versions-from-languages', ['--backup-dir' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'mera-v-'.Str::random(6)]);

        // Estaba publicada → sus versiones también; los links anteriores llevan a /<carpeta>/<año>/<versión>.
        $mx = CourseGroupVersion::where('course_group_id', $g['higiene']->id)->whereHas('market', fn ($q) => $q->where('code', 'MX'))->sole();
        $this->get('/introduccion-a-la-higiene')->assertRedirect("/introduccion-a-la-higiene/{$mx->year}/mexico");
        $this->get('/en/introduccion-a-la-higiene')->assertRedirect("/introduccion-a-la-higiene/{$mx->year}/eua");
        $this->followingRedirects()->get('/en/introduccion-a-la-higiene')->assertOk()->assertSee('Introduction to Hygiene');
    }
}
