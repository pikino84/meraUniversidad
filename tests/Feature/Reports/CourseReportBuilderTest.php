<?php

namespace Tests\Feature\Reports;

use App\Services\Reports\CourseReportBuilder;
use App\Services\Reports\OrgMapper;
use App\Services\Reports\PersonnelDirectory;
use App\Services\Reports\ReportFormatException;
use App\Services\Reports\ReportWorkbookReader;
use DateTimeImmutable;
use Tests\Concerns\BuildsSpreadsheets;
use Tests\TestCase;

/**
 * Armado de reportes con Excel SINTÉTICOS (INSTRUCTIVO_REPORTES.md §6 y §8).
 * La igualdad exacta con el script de referencia se comprueba con los Excel reales, solo en
 * local: php artisan mera:report-verify … --expected=… (§9).
 */
class CourseReportBuilderTest extends TestCase
{
    use BuildsSpreadsheets;

    private const PERSONNEL_HEADER = ['No. de empleado', 'Nombre', 'Dirección', 'Unidad de negocio', 'Departamento*', 'Puesto', 'Dirección'];

    private const ENROLLMENT_HEADER = ['No. Empleado', 'Nombre', 'Estatus', 'Progreso %', 'Calificación', 'Iniciado'];

    private CourseReportBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new CourseReportBuilder(new ReportWorkbookReader);
    }

    private function survey(array $overrides = []): array
    {
        return array_merge(config('report_surveys.higiene'), ['precoded_file' => null], $overrides);
    }

    private function surveyFile(array $rows): string
    {
        $header = ['No. Empleado', 'Estatus colaborador'];
        foreach (config('report_surveys.higiene.favorable') as [, $fragment]) {
            $header[] = "¿Pregunta: {$fragment}?";
        }
        $header[] = '¿Qué fue lo que más te gustó del curso?';
        $header[] = '¿Qué mejorarías del curso?';

        return $this->makeXlsx(array_merge([$header], $rows), 'encuesta.xlsx');
    }

    /** Respuestas favorables en las 8 preguntas cerradas de Higiene. */
    private const ALL_FAVORABLE = ['De acuerdo', 'Sí', 'Muy claros y aplicables', 'Sí, muchas', 'Sí', 'Fácil', 'Útiles', 'Si'];

    public function test_locality_and_area_rules(): void
    {
        $personnel = $this->makeXlsx([
            self::PERSONNEL_HEADER,
            [1, 'A', 'x', 'Panama', 'Cocina', 'cajero(a)', 'y'],
            [2, 'B', 'x', 'Mazatlán ', 'Restaurantes', 'MESERO', 'y'],
            [3, 'C', 'x', 'Cancún T2 Abt2', 'Almacen', 'almacenista', 'y'],
            [4, 'D', 'x', 'MERA Bogota', 'Bar', 'bartender', 'y'],
            [5, 'E', 'x', null, 'Ventas LATAM', 'ejecutivo', 'y'],      // sin unidad pero LATAM → Colombia
            [6, 'F', 'x', null, 'Contabilidad', 'contador', 'y'],       // sin unidad → se excluye
            [7, 'G', 'x', 'Cancun C4', 'Cocina', 'chef', 'y'],          // unidad excluida
            [8, 'H', 'x', 'Bajio', 'Limpieza FC', 'auxiliar', 'y'],     // área → otros
            [9, 'I', 'x', 'Bajio', 'Comisariato Food Court PB', 'cocinero', 'y'],
            [null, 'Sin número', 'x', 'Bajio', 'Cocina', 'chef', 'y'],
        ]);
        $enrollments = $this->makeXlsx(array_merge([self::ENROLLMENT_HEADER], array_map(
            fn ($n) => [$n, 'x', 'in_progress', 50, null, null],
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 99],
        )));

        $data = $this->builder->build($personnel, $enrollments, $this->surveyFile([]), $this->survey());
        $places = array_map(fn ($r) => "{$r[0]} | {$r[1]} | {$r[2]}", $data['cubo']['rows']);

        $this->assertContains('Panamá | Cocina | Cajero(A)', $places);
        $this->assertContains('Mazatlán | Piso / Restaurantes | Mesero', $places);
        $this->assertContains('Cancún T2 | Almacén | Almacenista', $places);
        $this->assertContains('Colombia | Bar | Bartender', $places);
        $this->assertContains('Colombia | Corporativo y administrativo / otros | Ejecutivo', $places);
        $this->assertContains('Bajío | Corporativo y administrativo / otros | Auxiliar', $places);
        $this->assertContains('Bajío | Comisariato | Cocinero', $places);
        $this->assertCount(7, $places); // 6 y 7 excluidos; 99 no está en personal
        $this->assertContains('2 inscritos de unidades excluidas o sin unidad (se omiten).', $data['warnings']);
        $this->assertContains('1 inscritos no están en el reporte de personal (se omiten).', $data['warnings']);
        $this->assertSame($places, collect($places)->sort(fn ($a, $b) => strcmp($a, $b))->values()->all()); // orden estable
    }

    public function test_cube_totals_grade_bands_and_start_months(): void
    {
        $personnel = $this->makeXlsx([self::PERSONNEL_HEADER, [10, 'A', 'x', 'Bajio', 'Cocina', 'chef', 'y'], [11, 'B', 'x', 'Bajio', 'Cocina', 'chef', 'y'],
            [12, 'C', 'x', 'Bajio', 'Cocina', 'chef', 'y'], [13, 'D', 'x', 'Bajio', 'Cocina', 'chef', 'y'], [14, 'E', 'x', 'Bajio', 'Cocina', 'chef', 'y'], [15, 'F', 'x', 'Bajio', 'Cocina', 'chef', 'y']]);
        $enrollments = $this->makeXlsx([
            self::ENROLLMENT_HEADER,
            [10, 'x', 'completed', 100, 95, new DateTimeImmutable('2026-01-10')],
            [11, 'x', 'completed', 100, 72, new DateTimeImmutable('2026-01-20')],
            [12, 'x', 'Completed ', 100, 61, new DateTimeImmutable('2026-02-03')],
            [13, 'x', 'completed', 100, 40, '2026-02-15'],                // fecha como texto
            [14, 'x', 'in_progress', 0, null, null],                     // no ha empezado
            [15.0, 'x', 'in_progress', 30, null, null],                  // en curso; número con ".0"
        ]);

        $data = $this->builder->build($personnel, $enrollments, $this->surveyFile([]), $this->survey());

        $this->assertSame([['Bajío', 'Cocina', 'Chef', 6, 4, 1, 4, 268.0, 1, 1, 1, 1]], $data['cubo']['rows']);
        $this->assertSame([['Bajío', 'Cocina', '2026-01', 2], ['Bajío', 'Cocina', '2026-02', 2]], $data['inicio_mes']['rows']);
        $this->assertSame(66.67, $data['summary']['pct_completado']);
        $this->assertSame(67.0, $data['summary']['calif_promedio']);
    }

    public function test_survey_flags_inactive_people_and_satisfaction(): void
    {
        $personnel = $this->makeXlsx([self::PERSONNEL_HEADER, [20, 'A', 'x', 'Bajio', 'Bar', 'barman', 'y'], [21, 'B', 'x', 'Bajio', 'Bar', 'barman', 'y']]);
        $enrollments = $this->makeXlsx([self::ENROLLMENT_HEADER, [20, 'x', 'completed', 100, 90, null]]);
        $notFavorable = self::ALL_FAVORABLE;
        $notFavorable[7] = 'No';

        $survey = $this->surveyFile([
            array_merge([20, 'active'], self::ALL_FAVORABLE, ['Los videos y los ejemplos', 'Nada, todo bien']),
            array_merge([21, 'deleted'], $notFavorable, ['Todo', '']),
            array_merge([999, 'active'], self::ALL_FAVORABLE, ['.', 'Que sea más corto']),
        ]);

        $data = $this->builder->build($personnel, $this->makeXlsx([self::ENROLLMENT_HEADER]), $survey, $this->survey());
        $columns = $data['encuesta']['columns'];
        $rows = array_map(fn ($r) => array_combine($columns, $r), $data['encuesta']['rows']);

        $this->assertSame(['Bajío', 'Bar'], [$rows[0]['localidad'], $rows[0]['area']]);
        $this->assertSame(['Ya no activo', 'Ya no activo'], [$rows[1]['localidad'], $rows[1]['area']]); // deleted
        $this->assertSame(['Ya no activo', 'Ya no activo'], [$rows[2]['localidad'], $rows[2]['area']]); // sin personal
        $this->assertSame(0, $rows[1]['f_recomienda']);
        $this->assertSame(66.67, $data['summary']['satisfaccion']); // 2 de 3 favorables en las 8

        // Propuesta por palabras clave (todas quedan pendientes: no hay clasificación previa).
        $this->assertSame([1, 1, 1, 0], [$rows[0]['v02'], $rows[0]['v03'], $rows[0]['v_respondio'], $rows[0]['m08']]);
        $this->assertSame([1, 1], [$rows[1]['v_todo'], $rows[1]['v_respondio']]);
        $this->assertSame([0, 1], [$rows[2]['v_respondio'], $rows[2]['m08']]);
        $this->assertCount(3, $data['pending']);
        $this->assertSame(['row' => 2, 'liked' => '.', 'improve' => 'Que sea más corto'], $data['pending'][2]);
        $this->assertArrayNotHasKey('num', $data['pending'][0]); // sin número de empleado
    }

    public function test_precoded_answers_take_priority_over_keywords(): void
    {
        $personnel = $this->makeXlsx([self::PERSONNEL_HEADER, [30, 'A', 'x', 'Bajio', 'Bar', 'barman', 'y']]);
        $codes = tempnam(sys_get_temp_dir(), 'cod');
        $header = array_merge(['no_empleado'], array_column(config('report_surveys.higiene.catalog'), 0), ['v_respondio']);
        $values = array_fill_keys($header, 0);
        $values['no_empleado'] = 30;
        $values['m06'] = 1;
        file_put_contents($codes, "\xEF\xBB\xBF".implode(',', $header)."\n".implode(',', $values)."\n");

        $data = $this->builder->build($personnel, $this->makeXlsx([self::ENROLLMENT_HEADER]),
            $this->surveyFile([array_merge([30, 'active'], self::ALL_FAVORABLE, ['Los videos', 'Más ejemplos'])]),
            $this->survey(['precoded_file' => $codes]));
        @unlink($codes);

        $row = array_combine($data['encuesta']['columns'], $data['encuesta']['rows'][0]);
        $this->assertSame([1, 0, 0], [$row['m06'], $row['v02'], $row['m07']]); // manda el CSV, no las palabras clave
        $this->assertSame([], $data['pending']);
    }

    public function test_a_missing_survey_question_is_reported_by_name(): void
    {
        $personnel = $this->makeXlsx([self::PERSONNEL_HEADER]);
        $survey = $this->makeXlsx([['No. Empleado', 'Estatus colaborador', 'Otra pregunta']]);

        $this->expectException(ReportFormatException::class);
        $this->expectExceptionMessage('Falta la columna «temas tratados son útiles» en el archivo «encuesta de satisfacción»');

        $this->builder->build($personnel, $this->makeXlsx([self::ENROLLMENT_HEADER]), $survey, $this->survey());
    }

    public function test_employee_numbers_and_title_case_match_python(): void
    {
        $this->assertSame('123', PersonnelDirectory::employeeNumber(123));
        $this->assertSame('123', PersonnelDirectory::employeeNumber(123.0));
        $this->assertSame('123', PersonnelDirectory::employeeNumber(' 123.0 '));
        $this->assertSame('A-7', PersonnelDirectory::employeeNumber('A-7'));
        $this->assertNull(PersonnelDirectory::employeeNumber(null));
        $this->assertSame('Cajero(A) De Línea 2Do', OrgMapper::titleCase('CAJERO(a) de línea 2do'));
    }
}
