<?php

namespace App\Services\Reports;

use Generator;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Lee la HOJA 1 de un Excel de reporte en streaming (poca memoria) y devuelve solo las columnas
 * pedidas (docs/reportes/INSTRUCTIVO_REPORTES.md §4).
 *
 * Las columnas se buscan por NOMBRE de encabezado, normalizado igual que el script de referencia
 * (generar_reporte.py): sin "*", espacios colapsados, sin acentos y en minúsculas. Nunca por
 * posición ni por color. Si un encabezado se repite (el reporte de personal trae dos
 * "Dirección"), se toma la PRIMERA coincidencia. Las filas totalmente vacías se ignoran.
 *
 * Recibe rutas (no el Request), como CourseManager, para poder moverse a un Job.
 */
class ReportWorkbookReader
{
    /**
     * @param  string  $label  nombre del archivo para los mensajes ("reporte de personal")
     * @param  array<string, array{0:string, 1?:bool}>  $columns  clave => [texto del encabezado, ¿exacto?]
     *                                                            exacto=false busca el texto como fragmento
     * @param  int  $headerScan  filas en las que se buscan los encabezados (el catálogo de cursos trae
     *                           títulos arriba); con 1, los encabezados deben estar en la primera fila
     * @return Generator<int, array<string, mixed>> filas con las claves pedidas
     *
     * @throws ReportFormatException
     */
    public function rows(string $path, string $label, array $columns, int $headerScan = 1): Generator
    {
        $options = new Options;
        $options->SHOULD_FORMAT_DATES = false;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = false;

        $reader = new Reader($options);

        try {
            $reader->open($path);
        } catch (Throwable $e) {
            throw new ReportFormatException("No se pudo abrir el archivo «{$label}». Verifica que sea un .xlsx válido.", 0, $e);
        }

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $indexes = null;
                $scanned = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $values = $row->toArray();

                    if ($indexes === null) {
                        $headers = array_map(fn ($h) => self::clean($h), $values);
                        // Última fila permitida: si faltan columnas, el error dice cuál.
                        $indexes = ++$scanned >= $headerScan
                            ? $this->locate($headers, $columns, $label)
                            : $this->tryLocate($headers, $columns);

                        continue;
                    }

                    if (! $this->hasContent($values)) {
                        continue;
                    }

                    $out = [];
                    foreach ($indexes as $key => $i) {
                        // Celda vacía = null, como openpyxl en el script de referencia.
                        $value = $values[$i] ?? null;
                        $out[$key] = $value === '' ? null : $value;
                    }

                    yield $out;
                }

                if ($indexes === null) {
                    throw new ReportFormatException("El archivo «{$label}» está vacío (no tiene encabezados).");
                }

                break; // solo la hoja 1
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Encabezados de la hoja 1 (primera fila), ya limpios. Sirve para saber qué preguntas trae una
     * encuesta antes de leerla: las que faltan se omiten en lugar de detener el corte.
     *
     * @return list<string>
     *
     * @throws ReportFormatException
     */
    public function headers(string $path, string $label): array
    {
        $reader = new Reader;

        try {
            $reader->open($path);
        } catch (Throwable $e) {
            throw new ReportFormatException("No se pudo abrir el archivo «{$label}». Verifica que sea un .xlsx válido.", 0, $e);
        }

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    return array_map(fn ($h) => self::clean($h), $row->toArray());
                }
                break;
            }
        } finally {
            $reader->close();
        }

        throw new ReportFormatException("El archivo «{$label}» está vacío (no tiene encabezados).");
    }

    /**
     * Posición de la primera columna que coincide con el texto (exacto o como fragmento, sin acentos
     * ni mayúsculas), o null. $headers puede venir limpio o ya normalizado.
     *
     * @param  list<string>  $headers
     */
    public static function findColumn(array $headers, string $text, bool $exact = false): ?int
    {
        $needle = self::normalize(self::clean($text));

        foreach ($headers as $i => $header) {
            $header = self::normalize($header);
            if ($exact ? $header === $needle : ($needle !== '' && str_contains($header, $needle))) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Texto del encabezado/celda sin "*" y con los espacios colapsados (limpiar() del script).
     */
    public static function clean(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        return trim(preg_replace('/\s+/u', ' ', str_replace('*', '', $text)));
    }

    /**
     * Forma comparable: sin acentos y en minúsculas (norm() del script).
     */
    public static function normalize(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    /**
     * @param  list<string>  $headers
     * @return array<string,int>|null null si a esta fila le falta alguna columna
     */
    private function tryLocate(array $headers, array $columns): ?array
    {
        try {
            return $this->locate($headers, $columns, '');
        } catch (ReportFormatException) {
            return null;
        }
    }

    /**
     * @param  list<string>  $headers
     * @return array<string,int>
     */
    private function locate(array $headers, array $columns, string $label): array
    {
        $normalized = array_map([self::class, 'normalize'], $headers);
        $indexes = [];

        foreach ($columns as $key => $spec) {
            [$text, $exact] = [$spec[0], $spec[1] ?? true];
            $found = self::findColumn($normalized, $text, $exact);

            if ($found === null) {
                throw new ReportFormatException("Falta la columna «{$text}» en el archivo «{$label}». Revisa que sea el archivo correcto.");
            }

            $indexes[$key] = $found;
        }

        return $indexes;
    }

    private function hasContent(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
