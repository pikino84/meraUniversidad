<?php

namespace App\Services\Reports;

use App\Models\CatalogCourse;
use App\Models\ProgressReportItem;
use Carbon\Carbon;
use DateTimeInterface;
use Throwable;

/**
 * Arma un corte del seguimiento anual (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §3 y §9) a partir de:
 *  - "Reporte de progreso de capacitaciones": una fila por persona × curso asignado (todos los cursos).
 *  - "Reporte de personal": quién sigue activo y su localidad/área/puesto (OrgMapper, mismas reglas
 *    que el reporte por curso). Quien no aparece en él se excluye como baja.
 *
 * Solo toma las columnas necesarias (§3): nunca nómina, RFC, CURP ni dirección. Devuelve arreglos
 * listos para insertar; no escribe en la base (eso lo hace ProgressReportManager).
 */
class ProgressReportBuilder
{
    private const COLUMNS = [
        'num' => ['No. Empleado'],
        'nombre' => ['Nombre'],
        'paterno' => ['Apellido Paterno'],
        'materno' => ['Apellido Materno'],
        'centro' => ['Centro de Trabajo'],
        'curso' => ['Nombre de Curso'],
        'horas' => ['Duración curso (hrs)'],
        'progreso' => ['Progreso global'],
        'iniciado' => ['Iniciado'],
        'completado' => ['Completado'],
        'calificacion' => ['Calificación Evaluación'],
    ];

    /** @var array<string, CatalogCourse|null> */
    private array $catalog = [];

    public function __construct(
        private readonly ReportWorkbookReader $reader,
        private readonly FastSheetReader $fastReader,
    ) {}

    /**
     * @return array{people: array<string, array<string,mixed>>, items: list<array<string,mixed>>, summary: array<string,mixed>, warnings: list<string>}
     *
     * @throws ReportFormatException
     */
    public function build(string $progressPath, string $personalPath): array
    {
        $directory = PersonnelDirectory::fromWorkbook($this->fastReader, $personalPath);
        $columns = array_map(fn ($c) => [$c[0], true], self::COLUMNS);

        $people = [];
        $items = [];
        $stats = ['rows' => 0, 'excluded_rows' => 0, 'inactive_rows' => 0, 'no_code_rows' => 0];
        $excluded = [];
        $inactive = [];
        $unknown = [];

        // El de progreso es grande (~75 mil filas): lector rápido. El de personal, el de siempre.
        foreach ($this->fastReader->rows($progressPath, 'reporte de progreso de capacitaciones', $columns) as $row) {
            $stats['rows']++;
            $num = PersonnelDirectory::employeeNumber($row['num']);

            if ($num === null || $num === '') {
                continue;
            }

            $org = $directory->find($num);

            if ($org === null) {
                $stats['inactive_rows']++;
                $inactive[$num] = true;

                continue;
            }

            if ($org['localidad'] === null) {
                $stats['excluded_rows']++;
                $excluded[$num] = true;

                continue;
            }

            $platformCode = CatalogCourse::codeFromName((string) $row['curso']);

            if ($platformCode === null) {
                $stats['no_code_rows']++;

                continue;
            }

            $course = $this->catalogCourse($platformCode);
            $year = 2000 + (int) substr(explode('-', $platformCode)[4], 0, 2);

            if (! $course && $year >= (int) config('mera.progress.tracking_from_year', 2026)) {
                $unknown[$platformCode] = ($unknown[$platformCode] ?? 0) + 1;
            }

            $progress = min(1.0, max(0.0, (float) $row['progreso']));
            $grade = is_numeric($row['calificacion']) ? round((float) $row['calificacion'], 2) : null;

            $people[$num] ??= [
                'employee_number' => $num,
                'full_name' => mb_substr(trim(preg_replace('/\s+/u', ' ', implode(' ', array_map(fn ($v) => ReportWorkbookReader::clean($v), [$row['nombre'], $row['paterno'], $row['materno']])))), 0, 200),
                'locality' => $org['localidad'],
                'work_center' => mb_substr(ReportWorkbookReader::clean($row['centro']), 0, 150) ?: null,
                'area' => $org['area'],
                'position' => mb_substr($org['puesto'], 0, 150) ?: 'Sin puesto',
            ];

            $items[] = [
                'employee_number' => $num,
                'catalog_course_id' => $course?->id,
                'platform_code' => $platformCode,
                'course_code' => $course?->code ?? $platformCode,
                'year' => $year,
                'progress' => round($progress, 4),
                'grade' => $grade,
                'hours' => is_numeric($row['horas']) ? round((float) $row['horas'], 2) : ($course?->hours ?? 0),
                'status' => ProgressReportItem::statusFor($course, $progress, $grade),
                'started_at' => $this->date($row['iniciado']),
                'completed_at' => $progress >= 1 ? $this->date($row['completado']) : null,
            ];
        }

        if ($stats['rows'] === 0) {
            throw new ReportFormatException('El reporte de progreso no tiene filas.');
        }

        $warnings = [];
        if ($excluded) {
            $warnings[] = count($excluded).' personas ('.$stats['excluded_rows'].' asignaciones) de unidades excluidas o sin unidad: no se cuentan.';
        }
        if ($inactive) {
            $warnings[] = count($inactive).' personas ('.$stats['inactive_rows'].' asignaciones) ya no están en el reporte de personal (bajas): no se cuentan.';
        }
        if ($unknown) {
            arsort($unknown);
            $warnings[] = 'Cursos que no están en el catálogo (se cuentan con mínima de 80): '.implode(', ', array_keys($unknown)).'. Agrégalos al catálogo o como código de la plataforma de un curso.';
        }
        if ($stats['no_code_rows']) {
            $warnings[] = $stats['no_code_rows'].' asignaciones sin código de curso al inicio del nombre: no se cuentan.';
        }

        return [
            'people' => $people,
            'items' => $items,
            'summary' => [
                'rows' => $stats['rows'],
                'items' => count($items),
                'people' => count($people),
                'excluded_people' => count($excluded),
                'inactive_people' => count($inactive),
                'unknown_codes' => $unknown,
                'years' => array_values(array_unique(array_column($items, 'year'))),
            ],
            'warnings' => $warnings,
        ];
    }

    private function catalogCourse(string $code): ?CatalogCourse
    {
        if (! array_key_exists($code, $this->catalog)) {
            $this->catalog[$code] = CatalogCourse::resolve($code);
        }

        return $this->catalog[$code];
    }

    /** Fecha de una celda (fecha de Excel, número de serie o texto) como Y-m-d. */
    private function date(mixed $value): ?string
    {
        try {
            return match (true) {
                $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                is_numeric($value) && $value > 20000 => Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString(),
                is_string($value) && trim($value) !== '' => Carbon::parse($value)->toDateString(),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }
}
