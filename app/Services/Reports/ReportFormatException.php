<?php

namespace App\Services\Reports;

use RuntimeException;

/**
 * Un Excel de reporte no tiene el formato esperado (falta una columna, no se puede leer…).
 * El mensaje es para el usuario: dice qué archivo y qué columna.
 */
class ReportFormatException extends RuntimeException {}
