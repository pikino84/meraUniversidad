<?php

namespace App\Services\Reports;

use App\Models\CatalogCourse;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Carga el catálogo de cursos desde "Catalogo_MERA_v*.xlsx" (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §6).
 *
 * - Lee la primera hoja; los encabezados están debajo de unas filas de título.
 * - El código se arma con Ruta, Sub, #, Mod, Año y País (la columna "Nomenclatura" es una fórmula
 *   que openspout no calcula). Las filas de sección ("▶ NORMATIVO") y las incompletas se saltan.
 * - Crea o actualiza por código los datos del Excel. NO toca lo que se edita en el panel (mínima,
 *   evaluación, si cuenta, códigos alternos) ni borra cursos que ya no vengan: los avisa.
 * - Al crear, aplica config('mera.progress.catalog_defaults') y deja fuera del seguimiento los cursos
 *   anteriores a config('mera.progress.tracking_from_year') (2026).
 * - Alcance (General/Específico, §7.3): columna "Alcance" en CUALQUIER hoja que identifique el curso
 *   (Ruta/Sub/#/Mod/Año/País o un código). Si dos hojas no coinciden, gana la primera (tabla de
 *   nomenclaturas) y se avisa. Es la excepción a "no pisar": el alcance SIEMPRE se toma del Excel. Si
 *   falta, respaldo con "Nivel" ("Todo MERA" = General) y aviso.
 *
 * Recibe rutas, no el Request (como ReportWorkbookReader).
 */
class CourseCatalogImporter
{
    /** Columnas del Excel que se toman (texto del encabezado). */
    private const COLUMNS = [
        'route' => ['Ruta'],
        'sub' => ['Sub'],
        'number' => ['#'],
        'modality' => ['Mod'],
        'year' => ['Año'],
        'country' => ['País'],
        'title' => ['Título Completo del Curso'],
        'reference' => ['NOM / Referencia'],
        'competency' => ['Competencia Asociada'],
        'level' => ['Nivel'],
        'hours' => ['Dur (h)'],
        'instructor' => ['Instructor'],
        'short_title' => ['Título Corto'],
    ];

    /** Lo que trae el Excel (lo demás es del panel). */
    private const FROM_EXCEL = [
        'route', 'sub', 'number', 'modality', 'year', 'country', 'title', 'short_title',
        'reference', 'competency', 'level', 'hours', 'instructor',
    ];

    public function __construct(private readonly ReportWorkbookReader $reader) {}

    /**
     * @return array{created: list<string>, updated: list<string>, unchanged: int, skipped: list<string>, missing: list<string>, scope_from_level: list<string>, scope_conflicts: list<string>}
     *
     * @throws ReportFormatException
     */
    public function import(string $path, bool $dryRun = false): array
    {
        $rows = $this->read($path);
        $scopes = $this->readScopes($path);
        $existing = CatalogCourse::all()->keyBy('code');
        $result = [
            'created' => [], 'updated' => [], 'unchanged' => 0, 'skipped' => $rows['skipped'], 'missing' => [],
            'scope_from_level' => [], 'scope_conflicts' => $scopes['conflicts'],
        ];

        foreach ($rows['courses'] as $code => $data) {
            if (isset($scopes['scopes'][$code])) {
                $rows['courses'][$code]['scope'] = $scopes['scopes'][$code];
            } else {
                $rows['courses'][$code]['scope'] = CatalogCourse::scopeFromLevel($data['level']);
                $result['scope_from_level'][] = $code;
            }
        }

        DB::transaction(function () use ($rows, $existing, $dryRun, &$result) {
            foreach ($rows['courses'] as $code => $data) {
                $course = $existing[$code] ?? new CatalogCourse(['code' => $code]
                    + (array) (config('mera.progress.catalog_defaults')[$code] ?? [])
                    + [
                        'passing_grade' => (int) config('mera.progress.passing_grade', 80),
                        'in_tracking' => $data['year'] >= (int) config('mera.progress.tracking_from_year', 2026),
                    ]);
                $course->fill($data);

                if (! $course->exists) {
                    $result['created'][] = $code;
                } elseif ($course->isDirty()) {
                    $result['updated'][] = $code;
                } else {
                    $result['unchanged']++;

                    continue;
                }

                if (! $dryRun) {
                    $course->save();
                }
            }

            $result['missing'] = $existing->keys()->diff(array_keys($rows['courses']))->values()->all();
        });

        return $result;
    }

    /**
     * @return array{courses: array<string, array<string,mixed>>, skipped: list<string>}
     */
    private function read(string $path): array
    {
        $courses = [];
        $skipped = [];
        $columns = array_map(fn ($c) => [$c[0], true], self::COLUMNS);

        foreach ($this->reader->rows($path, 'catálogo de cursos', $columns, (int) config('mera.progress.catalog_header_scan', 10)) as $row) {
            $row = array_map(fn ($v) => is_string($v) ? ReportWorkbookReader::clean($v) : $v, $row);

            // Filas de sección ("▶ NORMATIVO", "▸ SEG — Seguridad") o vacías: sin ruta.
            if (blank($row['route']) && blank($row['sub'])) {
                continue;
            }

            $title = (string) ($row['title'] ?? '');
            $code = (filled($row['number']) && is_numeric($row['number']) && is_numeric($row['year']) && filled($row['modality']))
                ? CatalogCourse::buildCode((string) $row['route'], (string) $row['sub'], (int) $row['number'], (string) $row['modality'], (int) $row['year'], (string) $row['country'])
                : null;

            if ($code === null || $title === '') {
                $skipped[] = trim(($row['route'] ?? '').' '.($row['sub'] ?? '').' '.($row['number'] ?? '').' · '.($title ?: 'sin título'));

                continue;
            }

            if (isset($courses[$code])) {
                $skipped[] = "{$code} (repetido en el Excel; se tomó el primero)";

                continue;
            }

            $country = strtoupper((string) $row['country']);

            $courses[$code] = [
                'route' => (string) $row['route'],
                'sub' => strtoupper((string) $row['sub']),
                'number' => (int) $row['number'],
                'modality' => strtoupper((string) $row['modality']),
                'year' => (int) $row['year'],
                'country' => $country !== '' && $country !== 'GLOBAL' ? $country : null,
                'title' => mb_substr($title, 0, 255),
                'short_title' => mb_substr(filled($row['short_title']) ? (string) $row['short_title'] : $title, 0, 150),
                'reference' => $this->text($row['reference'], 150),
                'competency' => $this->text($row['competency'], 150),
                'level' => $this->text($row['level'], 100),
                'hours' => is_numeric($row['hours']) ? round((float) $row['hours'], 2) : null,
                'instructor' => $this->text($row['instructor'], 150),
            ];
        }

        if ($courses === []) {
            throw new ReportFormatException('El archivo no tiene cursos con Ruta, Sub, #, Mod y Año. Revisa que sea el catálogo de cursos.');
        }

        return ['courses' => $courses, 'skipped' => $skipped];
    }

    /**
     * Alcance por código desde todas las hojas que lo traen.
     *
     * @return array{scopes: array<string,string>, conflicts: list<string>}
     */
    private function readScopes(string $path): array
    {
        $options = new Options;
        $options->SHOULD_FORMAT_DATES = false;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = false;
        $reader = new Reader($options);
        $scopes = [];
        $conflicts = [];
        $scan = (int) config('mera.progress.catalog_header_scan', 10);

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $map = null;
                $seen = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $values = $row->toArray();

                    if ($map === null) {
                        $map = $this->scopeColumns($values);
                        if ($map === null && ++$seen >= $scan) {
                            break; // esta hoja no tiene alcance
                        }

                        continue;
                    }

                    $scope = CatalogCourse::scopeFrom($values[$map['scope']] ?? null);
                    $code = $this->codeFromRow($values, $map);

                    if ($scope === null || $code === null) {
                        continue;
                    }

                    if (isset($scopes[$code]) && $scopes[$code] !== $scope) {
                        $conflicts[] = "{$code} (hoja «{$sheet->getName()}»)";

                        continue; // gana la primera hoja (nomenclaturas)
                    }

                    $scopes[$code] ??= $scope;
                }
            }
        } finally {
            $reader->close();
        }

        return ['scopes' => $scopes, 'conflicts' => array_values(array_unique($conflicts))];
    }

    /**
     * Índices de "Alcance" y de lo que identifica al curso en una fila de encabezados, o null.
     *
     * @return array<string,int>|null
     */
    private function scopeColumns(array $headers): ?array
    {
        $normalized = array_map(fn ($h) => ReportWorkbookReader::normalize(ReportWorkbookReader::clean($h)), $headers);
        $find = fn (string $text) => ($i = array_search(ReportWorkbookReader::normalize($text), $normalized, true)) === false ? null : $i;

        $map = array_filter([
            'scope' => $find('Alcance'),
            'route' => $find('Ruta'), 'sub' => $find('Sub'), 'number' => $find('#'),
            'modality' => $find('Mod'), 'year' => $find('Año'), 'country' => $find('País'),
            'code' => $find('Código') ?? $find('Nomenclatura') ?? $find('Código del curso'),
        ], fn ($i) => $i !== null);

        $byParts = isset($map['route'], $map['sub'], $map['number'], $map['modality'], $map['year']);

        return isset($map['scope']) && ($byParts || isset($map['code'])) ? $map : null;
    }

    private function codeFromRow(array $values, array $map): ?string
    {
        if (isset($map['route'], $map['sub'], $map['number'], $map['modality'], $map['year'])
            && is_numeric($values[$map['number']] ?? null) && is_numeric($values[$map['year']] ?? null)) {
            $code = CatalogCourse::buildCode(
                ReportWorkbookReader::clean($values[$map['route']] ?? ''), ReportWorkbookReader::clean($values[$map['sub']] ?? ''),
                (int) $values[$map['number']], ReportWorkbookReader::clean($values[$map['modality']] ?? ''),
                (int) $values[$map['year']], ReportWorkbookReader::clean($values[$map['country']] ?? ''),
            );

            if ($code !== null) {
                return $code;
            }
        }

        // Código escrito (no fórmula): "NRM-HIG-002-EL-26" o "NRM-HIG-002-EL-26 | Título".
        $text = isset($map['code']) ? (string) ($values[$map['code']] ?? '') : '';

        return str_starts_with($text, '=') ? null : CatalogCourse::codeFromName($text);
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /**
     * Agrega códigos alternos desde un CSV con columnas codigo_plataforma y codigo_catalogo_v12
     * (docs/reportes/equivalencias_codigos.csv). Las filas sin curso en el catálogo se devuelven.
     *
     * @return array{added: int, unmatched: list<string>}
     */
    public function importAliases(string $csvPath, bool $dryRun = false): array
    {
        $handle = fopen($csvPath, 'r');
        $header = array_map(fn ($h) => trim((string) $h, "\u{FEFF} "), (array) fgetcsv($handle));
        $added = 0;
        $unmatched = [];

        while (($line = fgetcsv($handle)) !== false) {
            $row = array_combine($header, array_pad($line, count($header), ''));
            $alias = strtoupper(trim($row['codigo_plataforma'] ?? ''));
            $target = strtoupper(trim($row['codigo_catalogo_v12'] ?? ''));
            $course = $target !== '' ? CatalogCourse::firstWhere('code', $target) : null;

            if (! $course || ! preg_match(CatalogCourse::CODE_PATTERN, $alias)) {
                $unmatched[] = $alias.($target !== '' ? " → {$target}" : ' (sin código del catálogo)');

                continue;
            }

            if (! in_array($alias, $course->aliases ?? [], true) && $alias !== $course->code) {
                $course->aliases = [...($course->aliases ?? []), $alias];
                $added++;

                if (! $dryRun) {
                    $course->save();
                }
            }
        }

        fclose($handle);

        return ['added' => $added, 'unmatched' => $unmatched];
    }
}
