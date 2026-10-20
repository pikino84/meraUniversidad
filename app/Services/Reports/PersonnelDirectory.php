<?php

namespace App\Services\Reports;

/**
 * No. de empleado → (localidad, área, puesto), a partir del "Reporte de personal"
 * (INSTRUCTIVO_REPORTES.md §6.1 y §6.2). Réplica de cargar_personal() del script de referencia;
 * las reglas están en OrgMapper (compartidas con el seguimiento anual).
 *
 * localidad = null significa "se excluye del reporte" (unidad excluida o sin unidad y no LATAM).
 * Solo vive en memoria mientras se arma el reporte: no se guarda nada de aquí.
 */
class PersonnelDirectory
{
    /** @param array<string, array{localidad: ?string, area: string, puesto: string}> $people */
    private function __construct(private readonly array $people) {}

    public static function fromWorkbook(ReportWorkbookReader|FastSheetReader $reader, string $path): self
    {
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

            // Si un número se repite, gana la última fila (como un dict de Python).
            $people[$num] = OrgMapper::map($row['uni'], $row['dep'], $row['pue']);
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
}
