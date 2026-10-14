<?php

namespace App\Services\Reports;

/**
 * No. de empleado → (localidad, área, puesto), a partir del "Reporte de personal"
 * (INSTRUCTIVO_REPORTES.md §6.1 y §6.2). Réplica de cargar_personal() del script de referencia.
 *
 * localidad = null significa "se excluye del reporte" (unidad excluida o sin unidad y no LATAM).
 * Solo vive en memoria mientras se arma el reporte: no se guarda nada de aquí.
 */
class PersonnelDirectory
{
    /** @param array<string, array{localidad: ?string, area: string, puesto: string}> $people */
    private function __construct(private readonly array $people) {}

    public static function fromWorkbook(ReportWorkbookReader $reader, string $path): self
    {
        $rules = config('mera.reports');
        $people = [];

        $rows = $reader->rows($path, 'reporte de personal', [
            'num' => ['No. de empleado'],
            'uni' => ['Unidad de negocio'],
            'dep' => ['Departamento'],
            'pue' => ['Puesto'],
        ]);

        foreach ($rows as $row) {
            $num = self::employeeNumber($row['num']);

            if ($num === null || $num === '') {
                continue; // filas sin número de empleado
            }

            $unit = ReportWorkbookReader::clean($row['uni']);
            $department = ReportWorkbookReader::clean($row['dep']);
            $position = ReportWorkbookReader::clean($row['pue']);

            if ($unit === '' || $unit === 'None') {
                $locality = preg_match($rules['colombia_without_unit'], $department.' '.$position) ? $rules['colombia'] : null;
            } else {
                $locality = in_array($unit, $rules['excluded_localities'], true) ? null : ($rules['localities'][$unit] ?? $unit);
            }

            // Si un número se repite, gana la última fila (como un dict de Python).
            $people[$num] = [
                'localidad' => $locality,
                'area' => $rules['areas'][$department] ?? $rules['area_other'],
                'puesto' => self::titleCase($position),
            ];
        }

        return new self($people);
    }

    /** @return array{localidad: ?string, area: string, puesto: string}|null */
    public function find(?string $employeeNumber): ?array
    {
        return $employeeNumber === null ? null : ($this->people[$employeeNumber] ?? null);
    }

    public function count(): int
    {
        return count($this->people);
    }

    /**
     * Número de empleado como texto, igual que num_emp() del script: "123", nunca "123.0".
     */
    public static function employeeNumber(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        $text = trim(is_scalar($value) ? (string) $value : '');

        return str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;
    }

    /**
     * Equivalente a str.title() de Python: mayúscula al inicio de cada tramo de letras y
     * minúsculas en el resto ("cajero(a)" → "Cajero(A)", "GERENTE DE" → "Gerente De").
     */
    public static function titleCase(string $text): string
    {
        return preg_replace_callback('/\p{L}+/u', fn ($m) => mb_strtoupper(mb_substr($m[0], 0, 1)).mb_strtolower(mb_substr($m[0], 1)), $text);
    }
}
