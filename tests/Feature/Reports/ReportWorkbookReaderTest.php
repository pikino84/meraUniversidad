<?php

namespace Tests\Feature\Reports;

use App\Services\Reports\ReportFormatException;
use App\Services\Reports\ReportWorkbookReader;
use DateTimeImmutable;
use Tests\Concerns\BuildsSpreadsheets;
use Tests\TestCase;

/**
 * Lector de los Excel de reportes (INSTRUCTIVO_REPORTES.md §4 y §8), con archivos sintéticos.
 */
class ReportWorkbookReaderTest extends TestCase
{
    use BuildsSpreadsheets;

    private ReportWorkbookReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new ReportWorkbookReader;
    }

    public function test_headers_are_matched_without_accents_asterisks_or_case(): void
    {
        $path = $this->makeXlsx([
            ['No. de Empleado*', 'Dirección', '  Unidad   de NEGOCIO ', 'Departamento', 'Puesto', 'Dirección'],
            [1001, 'Calle 1', 'Bajio', 'Cocina', 'cajero(a)', 'Otra calle'],
        ]);

        $rows = iterator_to_array($this->reader->rows($path, 'reporte de personal', [
            'num' => ['No. de empleado'],
            'uni' => ['Unidad de negocio'],
            'dir' => ['Direccion'],
        ]), false);

        $this->assertSame([['num' => 1001, 'uni' => 'Bajio', 'dir' => 'Calle 1']], $rows); // primera "Dirección"
    }

    public function test_columns_can_be_found_by_a_fragment_of_the_question(): void
    {
        $path = $this->makeXlsx([
            ['No. Empleado', '¿Qué fue lo que más te gustó del curso?', '¿Recomendarías este curso a un compañero?'],
            [7, 'Los videos', 'Sí'],
        ]);

        $rows = iterator_to_array($this->reader->rows($path, 'encuesta', [
            'gusto' => ['más te gustó', false],
            'recomienda' => ['Recomendarías este curso', false],
        ]), false);

        $this->assertSame([['gusto' => 'Los videos', 'recomienda' => 'Sí']], $rows);
    }

    public function test_empty_rows_are_skipped_and_dates_keep_their_type(): void
    {
        $path = $this->makeXlsx([
            ['No. Empleado', 'Iniciado'],
            [1, new DateTimeImmutable('2026-03-15 10:00:00')],
            [null, null],
            ['', ''],
            [2, null],
        ]);

        $rows = iterator_to_array($this->reader->rows($path, 'inscripciones', ['num' => ['No. Empleado'], 'ini' => ['Iniciado']]), false);

        $this->assertCount(2, $rows);
        $this->assertInstanceOf(DateTimeImmutable::class, $rows[0]['ini']);
        $this->assertSame('2026-03', $rows[0]['ini']->format('Y-m'));
        $this->assertNull($rows[1]['ini']);
    }

    public function test_a_missing_column_says_which_one_and_in_which_file(): void
    {
        $path = $this->makeXlsx([['No. Empleado', 'Estatus'], [1, 'completed']]);

        $this->expectException(ReportFormatException::class);
        $this->expectExceptionMessage('Falta la columna «Calificación» en el archivo «reporte de inscripciones»');

        iterator_to_array($this->reader->rows($path, 'reporte de inscripciones', [
            'num' => ['No. Empleado'],
            'cal' => ['Calificación'],
        ]));
    }

    public function test_a_file_that_is_not_xlsx_gives_a_clear_error(): void
    {
        $path = $this->makeXlsx([['x'], [1]]);
        file_put_contents($path, 'esto no es un Excel');

        $this->expectException(ReportFormatException::class);
        $this->expectExceptionMessage('No se pudo abrir el archivo «reporte de personal»');

        iterator_to_array($this->reader->rows($path, 'reporte de personal', ['num' => ['No. de empleado']]));
    }

    public function test_normalization_matches_the_reference_script(): void
    {
        $this->assertSame('no. de empleado', ReportWorkbookReader::normalize(ReportWorkbookReader::clean(' No.  de  Empleado* ')));
        $this->assertSame('que mejorarias del curso?', ReportWorkbookReader::normalize(ReportWorkbookReader::clean('¿Qué mejorarías del curso?')));
        $this->assertSame('', ReportWorkbookReader::clean(null));
    }
}
