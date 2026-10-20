<?php

namespace App\Services\Reports;

/**
 * Unidad de negocio, departamento y puesto → (localidad, área, puesto) con las reglas de
 * config('mera.reports') (INSTRUCTIVO_REPORTES.md §6.1 y §6.2). Lo usan el reporte por curso
 * (PersonnelDirectory) y el seguimiento anual (INSTRUCTIVO_REPORTE_PERSONAS.md §5): una sola regla.
 *
 * localidad = null significa "se excluye" (unidad excluida, o sin unidad y no LATAM).
 */
final class OrgMapper
{
    /**
     * @return array{localidad: ?string, area: string, puesto: string}
     */
    public static function map(mixed $unit, mixed $department, mixed $position): array
    {
        $rules = config('mera.reports');
        $unit = ReportWorkbookReader::clean($unit);
        $department = ReportWorkbookReader::clean($department);
        $position = ReportWorkbookReader::clean($position);

        if ($unit === '' || $unit === 'None') {
            $locality = preg_match($rules['colombia_without_unit'], $department.' '.$position) ? $rules['colombia'] : null;
        } else {
            $locality = in_array($unit, $rules['excluded_localities'], true) ? null : ($rules['localities'][$unit] ?? $unit);
        }

        return [
            'localidad' => $locality,
            'area' => $rules['areas'][$department] ?? $rules['area_other'],
            'puesto' => self::titleCase($position),
        ];
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
