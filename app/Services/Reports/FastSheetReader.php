<?php

namespace App\Services\Reports;

use Generator;
use XMLReader;
use ZipArchive;

/**
 * Lector rápido de la PRIMERA hoja de un .xlsx (XMLReader directo sobre el ZIP), para el Excel de
 * progreso del seguimiento anual: ~75 mil filas × 35 columnas. openspout tardaba ~140 s en leerlo
 * (procesa formato y estilo de cada celda); este, ~20 s (INSTRUCTIVO_REPORTE_PERSONAS.md §9).
 *
 * Misma forma de uso que ReportWorkbookReader::rows(): columnas por NOMBRE de encabezado (misma
 * normalización), solo las pedidas, filas vacías ignoradas. Diferencias: no interpreta formatos;
 * los números y las fechas llegan como texto numérico (las fechas, como número de serie de Excel).
 */
class FastSheetReader
{
    /**
     * @param  array<string, array{0:string, 1?:bool}>|null  $columns  null = todas, con el encabezado como clave
     *                                                                 (las encuestas: sus preguntas cambian por curso)
     * @return Generator<int, array<string, mixed>>
     *
     * @throws ReportFormatException
     */
    public function rows(string $path, string $label, ?array $columns): Generator
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ReportFormatException::inFile($label, "No se pudo abrir el archivo «{$label}»: no es un Excel válido (.xlsx) o está dañado. Ábrelo en Excel y guárdalo de nuevo como .xlsx.");
        }

        $sheet = $this->firstSheet($zip);
        $shared = $this->sharedStrings($zip);
        $zip->close();

        if ($sheet === null) {
            throw ReportFormatException::inFile($label, "El archivo «{$label}» no tiene hojas.");
        }

        $xml = new XMLReader;
        if (! @$xml->open('zip://'.$path.'#'.$sheet, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw ReportFormatException::inFile($label, "No se pudo leer la hoja 1 del archivo «{$label}»: guárdalo de nuevo como .xlsx desde Excel.");
        }

        $indexes = null;   // clave => índice de columna (0 = A)
        $wanted = [];      // índice de columna => clave
        $row = [];

        try {
            // Tras saltar una celda con next() el cursor ya está en el nodo siguiente (puede ser el
            // cierre de la fila): ese nodo se procesa sin volver a leer.
            $advance = true;

            while ($advance ? $xml->read() : true) {
                $advance = true;

                if ($xml->nodeType === XMLReader::END_ELEMENT && $xml->localName === 'row') {
                    if ($indexes === null) {
                        $headers = [];
                        foreach ($row as $i => $value) {
                            $headers[$i] = ReportWorkbookReader::clean($value);
                        }
                        $indexes = $columns === null ? $this->allColumns($headers) : $this->locate($headers, $columns, $label);
                        $wanted = array_flip($indexes);
                    } elseif ($row !== []) {
                        $out = [];
                        foreach ($indexes as $key => $i) {
                            $value = $row[$i] ?? null;
                            $out[$key] = $value === '' ? null : $value;
                        }
                        yield $out;
                    }
                    $row = [];

                    continue;
                }

                if ($xml->nodeType !== XMLReader::ELEMENT || $xml->localName !== 'c') {
                    continue;
                }

                $col = self::columnIndex((string) $xml->getAttribute('r'));

                if ($indexes !== null && ! isset($wanted[$col])) {
                    // Columna que no se pide: se salta sin leerla.
                    if (! $xml->next()) {
                        break;
                    }
                    $advance = false;

                    continue;
                }

                $value = $this->cellValue($xml, (string) $xml->getAttribute('t'), $shared);
                if ($value !== null && $value !== '') {
                    $row[$col] = $value;
                }
            }
        } finally {
            $xml->close();
        }

        if ($indexes === null) {
            throw ReportFormatException::inFile($label, "El archivo «{$label}» está vacío: la hoja 1 no tiene encabezados.");
        }
    }

    /**
     * Todas las columnas: encabezado => índice. Sin encabezado: "Columna N"; repetido: "<texto> (N)".
     *
     * @param  array<int,string>  $headers
     * @return array<string,int>
     */
    private function allColumns(array $headers): array
    {
        $indexes = [];
        foreach ($headers as $i => $header) {
            $key = $header === '' ? 'Columna '.($i + 1) : $header;
            $indexes[isset($indexes[$key]) ? "{$key} ({$i})" : $key] = $i;
        }

        return $indexes;
    }

    /** Índice de columna desde la referencia de celda ("AB12" → 27). */
    public static function columnIndex(string $ref): int
    {
        $n = 0;
        $len = strlen($ref);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($ref[$i]);
            if ($c < 65 || $c > 90) {
                break;
            }
            $n = $n * 26 + ($c - 64);
        }

        return $n - 1;
    }

    /** Valor de la celda actual: texto compartido, texto en línea, booleano o número (como texto). */
    private function cellValue(XMLReader $xml, string $type, array $shared): ?string
    {
        if ($xml->isEmptyElement) {
            return null;
        }

        $value = null;
        $depth = $xml->depth;

        while ($xml->read() && ! ($xml->nodeType === XMLReader::END_ELEMENT && $xml->depth === $depth)) {
            if ($xml->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if ($xml->localName === 'v') {
                $value = $xml->readString();
            } elseif ($xml->localName === 't' && $type === 'inlineStr') {
                $value = ($value ?? '').$xml->readString();
            }
        }

        return match ($type) {
            's' => $shared[(int) $value] ?? null,
            'b' => $value === '1' ? '1' : '0',
            default => $value,
        };
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $strings = [];
        $xml = new XMLReader;
        $xml->XML($content, null, LIBXML_NONET | LIBXML_COMPACT);
        $current = null;

        while ($xml->read()) {
            if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'si') {
                $current = '';
                if ($xml->isEmptyElement) {
                    $strings[] = '';
                    $current = null;
                }
            } elseif ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 't' && $current !== null) {
                $current .= $xml->readString();
            } elseif ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'rPh') {
                $xml->next(); // texto fonético (japonés): no es parte del valor
            } elseif ($xml->nodeType === XMLReader::END_ELEMENT && $xml->localName === 'si') {
                $strings[] = $current;
                $current = null;
            }
        }

        $xml->close();

        return $strings;
    }

    /** Ruta de la primera hoja según xl/workbook.xml y sus relaciones. */
    private function firstSheet(ZipArchive $zip): ?string
    {
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $rels = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (preg_match('/<(?:\w+:)?sheet[^>]*r:id="([^"]+)"/', $workbook, $sheet)) {
            preg_match_all('/<Relationship[^>]*>/', $rels, $relationships);

            foreach ($relationships[0] as $tag) {
                if (str_contains($tag, 'Id="'.$sheet[1].'"') && preg_match('/Target="([^"]+)"/', $tag, $target)) {
                    $path = ltrim($target[1], '/');

                    return str_starts_with($path, 'xl/') ? $path : 'xl/'.$path;
                }
            }
        }

        return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
    }

    /**
     * @param  array<int,string>  $headers
     * @return array<string,int>
     */
    private function locate(array $headers, array $columns, string $label): array
    {
        $normalized = array_map([ReportWorkbookReader::class, 'normalize'], $headers);
        $indexes = [];

        foreach ($columns as $key => $spec) {
            [$text, $exact] = [$spec[0], $spec[1] ?? true];
            $needle = ReportWorkbookReader::normalize(ReportWorkbookReader::clean($text));
            $found = null;

            foreach ($normalized as $i => $header) {
                if ($exact ? $header === $needle : ($needle !== '' && str_contains($header, $needle))) {
                    $found = $i;
                    break;
                }
            }

            if ($found === null) {
                throw ReportFormatException::inFile($label, "Falta la columna «{$text}» en el archivo «{$label}». ".ReportWorkbookReader::diagnose($headers, $label));
            }

            $indexes[$key] = $found;
        }

        return $indexes;
    }
}
