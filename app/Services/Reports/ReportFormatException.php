<?php

namespace App\Services\Reports;

use RuntimeException;
use Throwable;

/**
 * Un Excel de reporte no tiene el formato esperado (falta una columna, no se puede leer…).
 * El mensaje es para el usuario: dice qué archivo, qué falla y por qué. $workbook es la etiqueta del
 * archivo ("reporte de personal"…) para que quien lo atrapa agregue el nombre del archivo subido.
 */
class ReportFormatException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, public readonly ?string $workbook = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function inFile(string $workbook, string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous, $workbook);
    }

    /** Mismo error con el nombre del archivo que subió la persona («2026-10-22 Encuesta….xlsx»). */
    public function withUploadedName(string $name): self
    {
        return new self($this->getMessage()." Archivo subido: «{$name}».", $this->getCode(), null, $this->workbook);
    }
}
