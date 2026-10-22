<?php

namespace App\Services\Reports;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Arma los datos de un reporte de curso a partir de los 3 Excel (INSTRUCTIVO_REPORTES.md §6-§7).
 * Réplica exacta de generar_reporte.py: con los mismos archivos debe dar los mismos datos.
 *
 * Recibe RUTAS (no el Request), como CourseManager, para poder moverse a un Job.
 *
 * Resultado (nada de datos personales: solo agregados y banderas 0/1 por respuesta):
 *  - cubo, inicio_mes, encuesta, catalogo: {columns: [...], rows: [[...]]} (contrato con el JS, §7)
 *  - preguntas: las preguntas favorables que SÍ trae la encuesta ({clave, etiqueta}); el JS calcula la
 *    satisfacción con ellas (los cortes anteriores, sin esta tabla, usan las 8 de Higiene)
 *  - pending: respuestas abiertas sin clasificación previa, con la propuesta por palabras clave,
 *    para corregirlas en pantalla. Se guarda el texto, sin número de empleado ni nombre.
 *  - warnings: avisos para el usuario.
 *  - summary: totales para la lista de cortes.
 */
class CourseReportBuilder
{
    private const CUBE_FIELDS = ['asignados', 'completados', 'sin_iniciar', 'calif_n', 'calif_suma', 'b80', 'b70', 'b60', 'b50'];

    /** Largo máximo de cada comentario abierto que se guarda para clasificar. */
    private const MAX_ANSWER_LENGTH = 500;

    public function __construct(private readonly ReportWorkbookReader $reader) {}

    /**
     * @param  array  $survey  definición de config/report_surveys.php
     *
     * @throws ReportFormatException
     */
    public function build(string $personnelPath, string $enrollmentsPath, string $surveyPath, array $survey): array
    {
        $warnings = [];
        $personnel = PersonnelDirectory::fromWorkbook($this->reader, $personnelPath);

        [$cube, $months] = $this->cube($enrollmentsPath, $personnel, $warnings);
        $survey['catalog'] ??= (array) config('mera.progress.survey_topics'); // sin catálogo propio: el base
        [$responses, $pending, $summarySurvey, $questions] = $this->survey($surveyPath, $personnel, $survey, $warnings);

        $catalog = array_map(fn ($t) => [$t[0], $t[1], $t[2]], $survey['catalog']);

        return [
            'cubo' => ['columns' => array_merge(['localidad', 'area', 'puesto'], self::CUBE_FIELDS), 'rows' => $cube],
            'inicio_mes' => ['columns' => ['localidad', 'area', 'mes', 'n'], 'rows' => $months],
            'encuesta' => $responses,
            'catalogo' => ['columns' => ['codigo', 'tipo', 'etiqueta'], 'rows' => $catalog],
            'preguntas' => ['columns' => ['clave', 'etiqueta'], 'rows' => $questions],
            'pending' => $pending,
            'warnings' => $warnings,
            'summary' => $this->summary($cube) + $summarySurvey,
        ];
    }

    /** Título de la encuesta (columna "Cuestionario" de la primera respuesta), o null si no la trae. */
    public function surveyTitle(string $path): ?string
    {
        $label = 'encuesta de satisfacción';
        if (ReportWorkbookReader::findColumn($this->reader->headers($path, $label), 'Cuestionario', true) === null) {
            return null;
        }

        foreach ($this->reader->rows($path, $label, ['title' => ['Cuestionario']]) as $row) {
            return ReportWorkbookReader::clean($row['title']);
        }

        return null;
    }

    /** @return array{0: list<list<mixed>>, 1: list<list<mixed>>} */
    private function cube(string $path, PersonnelDirectory $personnel, array &$warnings): array
    {
        $rows = $this->reader->rows($path, 'reporte de inscripciones', [
            'num' => ['No. Empleado'],
            'prog' => ['Progreso %'],
            'cal' => ['Calificación'],
            'ini' => ['Iniciado'],
            'est' => ['Estatus'],
        ]);

        $cube = [];
        $months = [];
        $excluded = $missing = 0;

        foreach ($rows as $row) {
            $person = $personnel->find(PersonnelDirectory::employeeNumber($row['num']));

            if ($person === null) {
                $missing++;

                continue;
            }

            if ($person['localidad'] === null) {
                $excluded++;

                continue;
            }

            $key = $person['localidad']."\x1F".$person['area']."\x1F".$person['puesto'];
            $cube[$key] ??= array_fill_keys(self::CUBE_FIELDS, 0) + ['calif_suma' => 0.0];
            $g = &$cube[$key];
            $g['asignados']++;

            $progress = self::number($row['prog']) ?? 0.0;
            $completed = strtolower(trim(is_scalar($row['est']) ? (string) $row['est'] : 'none')) === 'completed';

            if ($completed) {
                $g['completados']++;
                $grade = self::number($row['cal']);

                if ($grade !== null) {
                    $g['calif_n']++;
                    $g['calif_suma'] += $grade;
                    $g[$grade >= 80 ? 'b80' : ($grade >= 70 ? 'b70' : ($grade >= 60 ? 'b60' : 'b50'))]++;
                }

                if ($started = self::date($row['ini'])) {
                    $monthKey = $person['localidad']."\x1F".$person['area']."\x1F".$started->format('Y-m');
                    $months[$monthKey] = ($months[$monthKey] ?? 0) + 1;
                }
            } elseif ($progress == 0) {
                $g['sin_iniciar']++;
            }

            unset($g);
        }

        if ($missing) {
            $warnings[] = "{$missing} inscritos no están en el reporte de personal (se omiten).";
        }
        if ($excluded) {
            $warnings[] = "{$excluded} inscritos de unidades excluidas o sin unidad (se omiten).";
        }

        // Mismo orden que sorted() de Python sobre tuplas de texto (por código de carácter).
        ksort($cube, SORT_STRING);
        ksort($months, SORT_STRING);

        $cubeRows = [];
        foreach ($cube as $key => $g) {
            $cubeRows[] = array_merge(explode("\x1F", $key), array_map(
                fn ($field) => $field === 'calif_suma' ? round($g[$field], 1) : $g[$field],
                self::CUBE_FIELDS,
            ));
        }

        $monthRows = [];
        foreach ($months as $key => $n) {
            $monthRows[] = array_merge(explode("\x1F", $key), [$n]);
        }

        return [$cubeRows, $monthRows];
    }

    /**
     * Únicas columnas obligatorias: las de la plataforma, que traen todas las encuestas. Las preguntas
     * cambian por curso (Respeto que Construye no trae la escala 0–10, 2026-10-22): ninguna pregunta
     * detiene el corte; si falta, su indicador no se calcula y se avisa.
     */
    private const REQUIRED_SURVEY_COLUMNS = [
        'num' => ['No. Empleado'],
        'est' => ['Estatus colaborador'],
    ];

    /** Fragmentos de respaldo de las preguntas abiertas, en orden de prioridad (cambian por curso). */
    private const LIKED_FRAGMENTS = ['más te gustó', 'te gustó más', 'más valioso'];

    private const IMPROVE_FRAGMENTS = ['mejorarías', 'podríamos mejorar'];

    /** @return array{0: array, 1: list<array>, 2: array, 3: list<array{0:string,1:?string}>} */
    private function survey(string $path, PersonnelDirectory $personnel, array $survey, array &$warnings): array
    {
        $label = 'encuesta de satisfacción';
        $headers = $this->reader->headers($path, $label);

        // Preguntas favorables: las que no vienen se omiten con aviso (antes detenían el corte, 2026-10-21).
        $favorable = [];
        $missing = [];
        foreach ($survey['favorable'] as $question) {
            ReportWorkbookReader::findColumn($headers, $question[1]) === null ? $missing[] = $question[1] : $favorable[] = $question;
        }
        if ($missing) {
            $warnings[] = 'La encuesta no trae '.(count($missing) === 1 ? 'la pregunta' : 'las preguntas').' «'.implode('», «', $missing).'»: '
                .($favorable ? 'la satisfacción se calcula con las otras '.count($favorable).'.' : 'no se calcula la satisfacción.');
        }

        $columns = self::REQUIRED_SURVEY_COLUMNS;
        foreach ($favorable as [$key, $fragment]) {
            $columns[$key] = [$fragment, false];
        }
        foreach (['liked' => [$survey['liked_question'] ?? null, ...self::LIKED_FRAGMENTS], 'improve' => [$survey['improve_question'] ?? null, ...self::IMPROVE_FRAGMENTS]] as $key => $fragments) {
            $fragment = collect($fragments)->filter()->first(fn ($f) => ReportWorkbookReader::findColumn($headers, $f) !== null);
            if ($fragment) {
                $columns[$key] = [$fragment, false];
            }
        }
        // Segunda pregunta de "lo que valoran" (p. ej. "¿Qué tema te pareció más útil?"): se suma al texto.
        $extra = $survey['liked_extra_question'] ?? null;
        if ($extra && ReportWorkbookReader::findColumn($headers, $extra) !== null) {
            $columns['liked2'] = [$extra, false];
        }

        $codeColumns = array_merge(
            array_values(array_filter(array_column($survey['catalog'], 0), fn ($c) => str_starts_with($c, 'm'))),
            array_values(array_filter(array_column($survey['catalog'], 0), fn ($c) => str_starts_with($c, 'v'))),
            ['v_respondio'],
        );

        $precoded = self::precoded($survey['precoded_file'] ?? null);
        $classifier = new SurveyAnswerClassifier($survey['catalog']);
        $inactive = config('mera.reports.inactive');

        $rows = [];
        $pending = [];
        $allFavorable = 0;
        $rated = 0; // respuestas con todas las preguntas claras (base de la satisfacción)

        foreach ($this->reader->rows($path, $label, $columns) as $row) {
            $row += ['liked' => null, 'improve' => null, 'liked2' => null];
            if (SurveyAnswerClassifier::meaningful($row['liked2'])) {
                $row['liked'] = trim(ReportWorkbookReader::clean($row['liked']).' · '.ReportWorkbookReader::clean($row['liked2']), ' ·');
            }
            $num = PersonnelDirectory::employeeNumber($row['num']);
            $person = $personnel->find($num);
            $deleted = strtolower(trim(is_scalar($row['est']) ? (string) $row['est'] : '')) === 'deleted';

            [$locality, $area] = ($deleted || $person === null)
                ? [$inactive, $inactive]
                : [$person['localidad'] ?? $inactive, $person['area']];

            // 'yes' = Sí/No escrito a mano: sin respuesta clara queda en null (fuera del %).
            $flags = [];
            foreach ($favorable as [$key, , $ok]) {
                $flags[] = $ok === 'yes'
                    ? (($yes = SurveyAnswerClassifier::yesNo($row[$key])) === null ? null : (int) $yes)
                    : (int) in_array(ReportWorkbookReader::clean($row[$key]), $ok, true);
            }
            if ($flags && ! in_array(null, $flags, true)) {
                $rated++;
                $allFavorable += (int) (array_sum($flags) === count($flags));
            }

            if ($num !== null && isset($precoded[$num])) {
                $codes = $precoded[$num];
            } elseif (! isset($columns['liked']) && ! isset($columns['improve']) && ! isset($columns['liked2'])) {
                $codes = []; // la encuesta no trae preguntas abiertas
            } else {
                $codes = $classifier->classify($row['liked'], $row['improve']);
                $pending[] = [
                    'row' => count($rows),
                    'liked' => mb_substr(ReportWorkbookReader::clean($row['liked']), 0, self::MAX_ANSWER_LENGTH),
                    'improve' => mb_substr(ReportWorkbookReader::clean($row['improve']), 0, self::MAX_ANSWER_LENGTH),
                ];
            }

            $rows[] = array_merge([$locality, $area], $flags, array_map(fn ($c) => (int) ($codes[$c] ?? 0), $codeColumns));
        }

        if ($pending) {
            $warnings[] = count($pending).' respuestas abiertas sin clasificar: el sistema propuso temas por palabras clave; revísalos en "Pendientes de clasificar".';
        }

        $total = count($rows);

        return [
            ['columns' => array_merge(['localidad', 'area'], array_column($favorable, 0), $codeColumns), 'rows' => $rows],
            $pending,
            ['respuestas' => $total, 'satisfaccion' => $rated ? round($allFavorable / $rated * 100, 2) : null],
            // Etiqueta null: cuenta para la satisfacción pero no lleva barra propia (p. ej. "recomienda" en Higiene).
            array_map(fn ($q) => [$q[0], $q[3] ?? null], $favorable),
        ];
    }

    /**
     * Clasificación previa (CSV privado): no_empleado → código => 0/1.
     *
     * @return array<string, array<string,int>>
     */
    public static function precoded(?string $file): array
    {
        if (! $file || ! is_file($file)) {
            return [];
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $map = [];

        if ($header) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // BOM

            while (($line = fgetcsv($handle)) !== false) {
                $record = array_combine($header, array_pad($line, count($header), ''));
                $num = PersonnelDirectory::employeeNumber($record['no_empleado'] ?? null);
                unset($record['no_empleado']);

                if ($num !== null && $num !== '') {
                    $map[$num] = array_map(fn ($v) => (int) ($v === '' ? 0 : $v), $record);
                }
            }
        }

        fclose($handle);

        return $map;
    }

    private function summary(array $cubeRows): array
    {
        $sum = array_fill_keys(self::CUBE_FIELDS, 0);
        foreach ($cubeRows as $row) {
            foreach (self::CUBE_FIELDS as $i => $field) {
                $sum[$field] += $row[$i + 3];
            }
        }

        return [
            'asignados' => $sum['asignados'],
            'completados' => $sum['completados'],
            'sin_iniciar' => $sum['sin_iniciar'],
            // 2 decimales: con 1 se redondea dos veces (79.46 → 79.5 → 80).
            'pct_completado' => $sum['asignados'] ? round($sum['completados'] / $sum['asignados'] * 100, 2) : null,
            'calif_promedio' => $sum['calif_n'] ? round($sum['calif_suma'] / $sum['calif_n'], 1) : null,
            'pct_80' => $sum['calif_n'] ? round($sum['b80'] / $sum['calif_n'] * 100, 2) : null,
        ];
    }

    /** float(v) de Python: número o null. */
    private static function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = is_string($value) ? trim($value) : '';

        return is_numeric($text) ? (float) $text : null;
    }

    /** a_fecha() del script, más el número de serie de Excel (fechas sin formato de fecha). */
    private static function date(mixed $value): ?DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if ((is_int($value) || is_float($value)) && $value > 20000 && $value < 80000) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) floor($value).' days');
        }

        $text = is_string($value) ? trim($value) : '';
        foreach (['Y-m-d H:i:s', 'Y-m-d', 'd/m/Y H:i', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $text);
            if ($date !== false && $date->format($format) === $text) {
                return $date;
            }
        }

        return null;
    }
}
