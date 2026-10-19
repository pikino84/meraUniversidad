<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseGroupVersion;
use App\Models\CourseReport;
use App\Models\Market;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Acomoda lo existente en versiones (docs/carpetas/INSTRUCTIVO_VERSIONES.md §6 y respuestas §9):
 * cada carpeta + idioma con módulos pasa a una versión (carpeta + año + país).
 *
 *  - Año: el del código del nombre de la carpeta ("NRM-SEG-001-EL-25 | …" → 2025); sin código, --year.
 *  - País: Inglés → --en (EUA). Español → el sufijo del código si es una versión conocida (-MX → México);
 *    si no, --es (México), salvo lo indicado en --map (p. ej. manipulacion-de-alimentos:LATAM).
 *  - Copia a la versión la descripción, los links de evaluación/encuesta, si estaba publicada y, en
 *    inglés, name_en como título. El código del catálogo de la carpeta pasa a la versión.
 *  - Los cortes de reporte se asignan a la versión en español del año de su fecha de corte (o a la
 *    única versión de la carpeta).
 *  - No toca módulos sin carpeta, no fusiona carpetas y no cambia ninguna URL de módulo.
 *  - Idempotente: si la versión ya existe no la duplica; un módulo que ya tiene versión no se mueve.
 *
 * Antes de correrlo sin --dry-run: mysqldump (CLAUDE.md regla 2). El comando además guarda un JSON
 * con el estado previo en storage/backups/.
 *
 *   php artisan mera:versions-from-languages --dry-run
 *   php artisan mera:versions-from-languages --map=manipulacion-de-alimentos:LATAM
 */
class VersionsFromLanguages extends Command
{
    /** Código del catálogo al inicio del nombre: "NRM-HIG-002-EL-26 | Título" (INSTRUCTIVO_REPORTE_PERSONAS.md §4). */
    public const CODE_PATTERN = '/^\s*((?:NRM|NEG|CMP)-[A-Z]{3}-\d{3}-(?:EL|BL)-(\d{2})(?:-([A-Z]{2,}))?)\s*\|/u';

    protected $signature = 'mera:versions-from-languages
        {--dry-run : Solo mostrar lo que haría}
        {--year= : Año para carpetas sin código en el nombre (por defecto, el actual)}
        {--es=MX : Versión para lo que está en Español}
        {--en=US : Versión para lo que está en Inglés}
        {--map=* : Excepciones por carpeta: slug:CÓDIGO (p. ej. manipulacion-de-alimentos:LATAM)}
        {--backup-dir= : Carpeta del respaldo JSON (por defecto storage/backups)}';

    protected $description = 'Pasa las carpetas de Español/Inglés a versiones por año y país (idempotente)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fallbackYear = (int) ($this->option('year') ?: now(config('mera.display_timezone'))->year);
        $markets = Market::all()->keyBy('code');
        $map = $this->parseMap((array) $this->option('map'), $markets);

        foreach (['es', 'en'] as $language) {
            if (! $markets->has($this->option($language))) {
                $this->error("No existe la versión «{$this->option($language)}» (--{$language}). Revisa la pantalla Versiones.");

                return self::FAILURE;
            }
        }

        if ($map === null) {
            return self::FAILURE;
        }

        $plan = $this->plan($fallbackYear, $markets, $map);

        if ($plan === []) {
            $this->info('No hay nada que acomodar: todos los módulos con carpeta ya tienen versión.');

            return self::SUCCESS;
        }

        $this->table(
            ['Carpeta', 'Idioma', 'Año', 'Versión', 'Código', 'Módulos', 'Publicada', 'Nueva'],
            array_map(fn ($p) => [
                $p['group']->name, $p['language'], $p['year'], $p['market']->name, $p['code'] ?? '—',
                count($p['courses']), $p['published'] ? 'Sí' : 'No', $p['exists'] ? 'Ya existe' : 'Sí',
            ], $plan),
        );

        if ($dryRun) {
            $this->line('[dry-run] No se escribió nada.');

            return self::SUCCESS;
        }

        $backup = $this->backup();
        $this->line("Respaldo del estado previo: {$backup}");

        DB::transaction(fn () => $this->apply($plan));

        $this->info('Listo: '.count($plan).' versiones acomodadas. Las páginas publicadas siguen publicadas en su versión.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, Market>|null slug de carpeta => versión, o null si hay un error
     */
    private function parseMap(array $entries, $markets): ?array
    {
        $map = [];

        foreach ($entries as $entry) {
            foreach (array_filter(array_map('trim', explode(',', (string) $entry))) as $pair) {
                [$slug, $code] = array_pad(explode(':', $pair, 2), 2, '');

                if (! $markets->has($code)) {
                    $this->error("--map: la versión «{$code}» no existe (en «{$pair}»).");

                    return null;
                }

                $map[$slug] = $markets[$code];
            }
        }

        return $map;
    }

    /**
     * @return list<array{group: CourseGroup, language: string, year: int, market: Market, code: ?string, courses: \Illuminate\Support\Collection, published: bool, exists: bool}>
     */
    private function plan(int $fallbackYear, $markets, array $map): array
    {
        $plan = [];

        $groups = CourseGroup::with(['modules' => fn ($q) => $q->whereNull('course_group_version_id')->whereNotNull('language')->ordered()])
            ->orderBy('position')->get();

        foreach ($groups as $group) {
            [$code, $year, $suffix] = self::parseCode($group->name);
            $year ??= $fallbackYear;

            foreach ($group->modules->groupBy('language') as $language => $courses) {
                $market = match (true) {
                    $language === 'en' => $markets[$this->option('en')],
                    isset($map[$group->slug]) => $map[$group->slug],
                    $suffix !== null && $markets->has($suffix) => $markets[$suffix],
                    default => $markets[$this->option('es')],
                };

                $plan[] = [
                    'group' => $group,
                    'language' => $language,
                    'year' => $year,
                    'market' => $market,
                    'code' => $code,
                    'courses' => $courses,
                    'published' => (bool) $group->is_published,
                    'exists' => $group->versions()->where('year', $year)->where('market_id', $market->id)->exists(),
                ];
            }
        }

        return $plan;
    }

    private function apply(array $plan): void
    {
        foreach ($plan as $p) {
            /** @var CourseGroup $group */
            $group = $p['group'];
            $suffix = $p['language'];

            $version = $group->versions()->firstOrNew(['year' => $p['year'], 'market_id' => $p['market']->id]);

            if (! $version->exists) {
                $version->fill([
                    'catalog_code' => $p['code'],
                    'title' => $suffix === 'en' ? $group->name_en : null,
                    'description' => $group->{"description_{$suffix}"},
                    'evaluation_url' => $group->{"evaluation_url_{$suffix}"} ?: $group->evaluation_url_es,
                    'survey_url' => $group->{"survey_url_{$suffix}"} ?: $group->survey_url_es,
                ]);
                $version->forceFill([
                    'is_published' => (bool) $group->is_published,
                    'published_at' => $group->published_at,
                ])->save();
            }

            // Conserva el orden actual (la posición ya era por carpeta + idioma).
            foreach ($p['courses']->values() as $i => $course) {
                $course->forceFill([
                    'course_group_version_id' => $version->id,
                    'language' => $version->language(),
                    'position' => $i + 1,
                ])->save();
            }
        }

        // Cortes de reporte sin versión: la versión en español del año del corte, o la única.
        CourseReport::whereNull('course_group_version_id')->get()->each(function (CourseReport $report) {
            $versions = CourseGroupVersion::with('market')->where('course_group_id', $report->course_group_id)->get();
            $match = $versions->first(fn ($v) => $v->year === $report->cut_date->year && $v->language() === 'es')
                ?? $versions->first(fn ($v) => $v->language() === 'es')
                ?? ($versions->count() === 1 ? $versions->first() : null);

            if ($match) {
                $report->forceFill(['course_group_version_id' => $match->id])->saveQuietly();
            }
        });
    }

    private function backup(): string
    {
        $dir = $this->option('backup-dir') ?: storage_path('backups');
        $path = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'versiones_antes_'.now()->format('Ymd_His').'.json';
        File::ensureDirectoryExists(dirname($path));

        File::put($path, json_encode([
            'courses' => Course::withTrashed()->whereNotNull('course_group_id')->get(['id', 'course_group_id', 'course_group_version_id', 'language', 'position']),
            'course_reports' => CourseReport::get(['id', 'course_group_id', 'course_group_version_id', 'cut_date']),
            'course_group_versions' => CourseGroupVersion::get(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    /**
     * Código del catálogo al inicio del nombre de una carpeta.
     *
     * @return array{0: ?string, 1: ?int, 2: ?string} [código, año, sufijo de país]
     */
    public static function parseCode(string $name): array
    {
        if (! preg_match(self::CODE_PATTERN, $name, $m)) {
            return [null, null, null];
        }

        return [$m[1], 2000 + (int) $m[2], ($m[3] ?? '') !== '' ? $m[3] : null];
    }
}
