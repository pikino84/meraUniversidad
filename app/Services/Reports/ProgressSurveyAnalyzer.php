<?php

namespace App\Services\Reports;

use Illuminate\Support\Str;

/**
 * Encuesta de satisfacción de un curso para el seguimiento anual (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md §6.1).
 *
 * Todas las encuestas de la plataforma comparten 10 columnas fijas (cuestionario, fecha, No. Empleado,
 * colaborador, estatus…) y luego sus preguntas, que cambian por curso. Sin configurar nada se obtienen:
 *  - indicadores comunes: % que recomienda (Sí/No), NPS (escala 0–10) y % con problemas técnicos;
 *  - la distribución de cada pregunta cerrada (pocas respuestas distintas);
 *  - las respuestas abiertas (solo el texto: nunca nombre ni número de empleado).
 * Si config/report_surveys.php tiene la encuesta del curso (course_codes), además la satisfacción con
 * la misma regla del reporte por curso: % que respondió favorable TODAS las preguntas configuradas.
 *
 * Las respuestas traen espacios al final ("Mucho  "): siempre se recortan.
 */
class ProgressSurveyAnalyzer
{
    /** Columnas fijas de la plataforma (no son preguntas). */
    private const FIXED = ['cuestionario', 'fecha', 'no. empleado', 'colaborador', 'estatus colaborador', 'departamento', 'puesto', 'centro de trabajo', 'entidad legal', 'resultado'];

    /** Una pregunta cerrada tiene a lo más N respuestas distintas (ver isClosed). */
    private const MAX_CLOSED_OPTIONS = 8;

    private const MAX_ANSWER_LENGTH = 500;

    public function __construct(private readonly FastSheetReader $reader) {}

    /**
     * "employees" solo vive en memoria (para cruzar con el corte): no se guarda.
     *
     * @return array{title: string, responses: int, active: int, employees: list<string>, data: array, warnings: list<string>}
     *
     * @throws ReportFormatException
     */
    public function analyze(string $path, ?string $courseCode = null): array
    {
        $title = null;
        $answers = [];      // pregunta => lista de respuestas (texto recortado)
        $employees = [];
        $rowEmployees = []; // No. de empleado por fila, solo en memoria (clasificación previa de Higiene)
        $responses = 0;
        $active = 0;

        foreach ($this->reader->rows($path, 'encuesta de satisfacción', null) as $row) {
            $responses++;
            $title ??= ReportWorkbookReader::clean($this->value($row, 'cuestionario'));
            $active += Str::lower(trim((string) $this->value($row, 'estatus colaborador'))) === 'deleted' ? 0 : 1;

            $num = PersonnelDirectory::employeeNumber($this->value($row, 'no. empleado'));
            $rowEmployees[] = $num;
            if ($num) {
                $employees[] = $num;
            }

            foreach ($row as $question => $value) {
                if (in_array(Str::lower($question), self::FIXED, true)) {
                    continue;
                }
                $answers[$question][] = trim(preg_replace('/\s+/u', ' ', (string) $value));
            }
        }

        if ($responses === 0) {
            throw new ReportFormatException('La encuesta no tiene respuestas.');
        }

        if (blank($title) || $employees === []) {
            throw new ReportFormatException('No parece una encuesta de satisfacción de la plataforma (faltan «Cuestionario» o «No. Empleado»).');
        }

        $indicators = ['recommend' => null, 'nps' => null, 'nps_avg' => null, 'tech_problems' => null];
        $closed = [];
        $open = [];

        foreach ($answers as $question => $list) {
            $filled = array_values(array_filter($list, fn ($a) => $a !== ''));
            if ($filled === []) {
                continue; // bienvenida y cierre
            }

            $q = Str::lower(Str::ascii($question));
            $distinct = array_count_values($filled);
            arsort($distinct);

            if (str_contains($q, '0 al 10') && $this->allNumeric($filled)) {
                $scores = array_map('intval', $filled);
                $n = count($scores);
                $promoters = count(array_filter($scores, fn ($s) => $s >= 9));
                $detractors = count(array_filter($scores, fn ($s) => $s <= 6));
                $indicators['nps'] = (int) round(($promoters - $detractors) / $n * 100);
                $indicators['nps_avg'] = round(array_sum($scores) / $n, 1);
                $indicators['nps_n'] = $n;

                continue;
            }

            // "¿Cómo calificarías el curso en general?" (0–10, Respeto que Construye): NO es NPS; promedio.
            if (str_contains($q, 'calificarias') && $this->allNumeric($filled)) {
                $indicators['rating_avg'] = round(array_sum(array_map('intval', $filled)) / count($filled), 1);
                $indicators['rating_n'] = count($filled);

                continue;
            }

            // "¿Recomendarías…?" escrita a mano (abierta): Sí / No / sin respuesta clara (fuera del %).
            if (str_contains($q, 'recomendar') && ! $this->isClosed($filled, $distinct)) {
                $groups = ['Sí' => 0, 'No' => 0, 'Sin respuesta clara' => 0];
                foreach ($filled as $answer) {
                    $groups[match (SurveyAnswerClassifier::yesNo($answer)) {
                        true => 'Sí', false => 'No', null => 'Sin respuesta clara'
                    }]++;
                }
                if ($groups['Sí'] + $groups['No']) {
                    $indicators['recommend'] = round($groups['Sí'] / ($groups['Sí'] + $groups['No']) * 100, 1);
                }
                $closed[] = ['question' => $question, 'answers' => array_filter($groups), 'n' => count($filled)];

                continue;
            }

            if ($this->isClosed($filled, $distinct)) {
                $yes = $this->yesShare($distinct);

                if (str_contains($q, 'recomendar') && $yes !== null) {
                    $indicators['recommend'] = $yes;
                } elseif (str_contains($q, 'problema') && str_contains($q, 'tecnic') && $yes !== null) {
                    $indicators['tech_problems'] = $yes;
                }

                $closed[] = ['question' => $question, 'answers' => $distinct, 'n' => count($filled)];

                continue;
            }

            $open[] = ['question' => $question, 'answers' => array_map(fn ($a) => mb_substr($a, 0, self::MAX_ANSWER_LENGTH), $filled)];
        }

        [$satisfaction, $configWarnings] = $this->configuredSatisfaction($courseCode, $answers, $responses);
        $topics = $this->topics($courseCode, $answers, $rowEmployees);

        return [
            'title' => (string) $title,
            'responses' => $responses,
            'active' => $active,
            'employees' => array_values(array_unique($employees)),
            'data' => ['indicators' => $indicators + ['satisfaction' => $satisfaction], 'closed' => $closed, 'open' => $open, 'topics' => $topics],
            'warnings' => $configWarnings,
        ];
    }

    /**
     * Curso más parecido al título ("Encuesta de Satisfacción - <título>" o el nombre del archivo).
     *
     * @param  array<string,string>  $candidates  código => nombre(s) del curso
     */
    public static function guessCourse(string $title, array $candidates): ?string
    {
        $clean = fn (string $t) => trim(preg_replace('/[^a-z0-9 ]+/', ' ', Str::lower(Str::ascii(
            preg_replace('/^.*encuesta de satisfacci[oó]n\s*-\s*/iu', '', preg_replace('/\.xlsx$|responses?$/i', '', $t))
        ))));
        $words = fn (string $t) => array_values(array_filter(explode(' ', $clean($t)), fn ($w) => strlen($w) > 2 && ! in_array($w, ['curso', 'del', 'las', 'los', 'por', 'para', 'con', 'una', 'tus', 'que'], true)));

        $target = $words($title);
        if ($target === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($candidates as $code => $name) {
            $theirs = $words($name);
            if ($theirs === []) {
                continue;
            }
            $common = count(array_intersect($target, $theirs));
            $score = $common / max(count($target), count($theirs));

            if ($score > $bestScore) {
                [$best, $bestScore] = [$code, $score];
            }
        }

        return $bestScore >= 0.34 ? $best : null;
    }

    /**
     * Cerrada: pocas respuestas distintas que se repiten y son cortas. Abierta: muchas distintas, ninguna
     * repetida o largas (con pocas respuestas, una abierta también tiene pocas distintas).
     */
    private function isClosed(array $filled, array $distinct): bool
    {
        $n = count($filled);
        $avgLength = array_sum(array_map('mb_strlen', $filled)) / max(1, $n);

        return count($distinct) <= self::MAX_CLOSED_OPTIONS
            && count($distinct) < $n
            && $avgLength <= 40;
    }

    /** % de "Sí" en una pregunta Sí/No; null si no lo es. */
    private function yesShare(array $distinct): ?float
    {
        $yes = 0;
        $no = 0;
        foreach ($distinct as $answer => $count) {
            $a = Str::lower(Str::ascii((string) $answer));
            if ($a === 'si' || str_starts_with($a, 'si,') || str_starts_with($a, 'si ')) {
                $yes += $count;
            } elseif ($a === 'no' || str_starts_with($a, 'no,') || str_starts_with($a, 'no ')) {
                $no += $count;
            } else {
                return null;
            }
        }

        return $yes + $no ? round($yes / ($yes + $no) * 100, 1) : null;
    }

    private function allNumeric(array $values): bool
    {
        foreach ($values as $v) {
            if (! is_numeric($v) || $v < 0 || $v > 10) {
                return false;
            }
        }

        return true;
    }

    /**
     * Satisfacción configurada (config/report_surveys.php con course_codes): % de respuestas con
     * TODAS las preguntas favorables, como el reporte por curso.
     *
     * @return array{0: ?float, 1: list<string>}
     */
    private function configuredSatisfaction(?string $courseCode, array $answers, int $responses): array
    {
        $config = collect(config('report_surveys'))->first(fn ($s) => $courseCode && in_array($courseCode, $s['course_codes'] ?? [], true));

        if (! $config) {
            return [null, []];
        }

        $columns = [];
        $missing = [];
        foreach ($config['favorable'] as [$key, $fragment, $ok]) {
            $question = collect(array_keys($answers))->first(fn ($q) => str_contains(Str::lower(Str::ascii($q)), Str::lower(Str::ascii($fragment))));
            $question ? $columns[] = [$question, $ok] : $missing[] = $fragment;
        }

        // Como el reporte por curso: lo que falta se omite con aviso y se calcula con lo demás.
        $warnings = $missing ? ['La encuesta no trae '.(count($missing) === 1 ? 'la pregunta' : 'las preguntas').' «'.implode('», «', $missing).'»: '
            .($columns ? 'la satisfacción se calcula con las otras '.count($columns).'.' : 'no se calcula la satisfacción.')] : [];

        if ($columns === []) {
            return [null, $warnings];
        }

        // Igual que el reporte por curso: 'yes' = Sí/No escrito a mano; sin respuesta clara, fuera del %.
        $favorable = 0;
        $rated = 0;
        for ($i = 0; $i < $responses; $i++) {
            $all = true;
            $clear = true;
            foreach ($columns as [$question, $ok]) {
                $answer = $answers[$question][$i] ?? '';
                $yes = $ok === 'yes' ? SurveyAnswerClassifier::yesNo($answer) : in_array($answer, $ok, true);
                $clear = $clear && $yes !== null;
                $all = $all && $yes === true;
            }
            $rated += (int) $clear;
            $favorable += (int) ($clear && $all);
        }

        return [$rated ? round($favorable / $rated * 100, 1) : null, $warnings];
    }

    /**
     * Temas de "lo que más te gustó" y "qué mejorarías" (§6.1.5): catálogo del curso si está configurado
     * (Higiene, con su clasificación previa por persona) o el catálogo base. Las palabras clave PROPONEN;
     * en "Clasificar comentarios" se corrige. Se guarda el texto y sus temas, nunca quién respondió.
     *
     * @return array{catalog: list<array{0:string,1:string,2:string}>, answers: list<array>, liked_n: int, improve_n: int}|null
     */
    private function topics(?string $courseCode, array $answers, array $rowEmployees): ?array
    {
        $config = collect(config('report_surveys'))->first(fn ($s) => $courseCode && in_array($courseCode, $s['course_codes'] ?? [], true));
        $catalog = $config['catalog'] ?? (array) config('mera.progress.survey_topics');
        // El fragmento más específico manda (Despega tiene "lo que más influyó…" antes de "lo que más te gustó").
        $find = function (array $fragments) use ($answers): ?string {
            foreach (array_filter($fragments) as $fragment) {
                foreach (array_keys($answers) as $question) {
                    if (str_contains(Str::lower(Str::ascii($question)), Str::lower(Str::ascii($fragment)))) {
                        return $question;
                    }
                }
            }

            return null;
        };

        $likedQ = $find([$config['liked_question'] ?? null, 'mas te gusto', 'te gusto mas', 'mas valioso', 'lo que mas']);
        $improveQ = $find([$config['improve_question'] ?? null, 'mejorarias', 'que mejorar', 'podriamos mejorar']);

        if (! $likedQ && ! $improveQ) {
            return null;
        }

        $precoded = $config ? CourseReportBuilder::precoded($config['precoded_file'] ?? null) : [];
        $classifier = new SurveyAnswerClassifier($catalog);
        $codes = array_column($catalog, 0);
        $list = [];
        $likedN = 0;
        $improveN = 0;

        foreach ($rowEmployees as $i => $num) {
            $liked = $likedQ ? ($answers[$likedQ][$i] ?? '') : '';
            $improve = $improveQ ? ($answers[$improveQ][$i] ?? '') : '';
            $likedOk = SurveyAnswerClassifier::meaningful($liked);
            $improveOk = SurveyAnswerClassifier::meaningful($improve, true);

            if (! $likedOk && ! $improveOk) {
                continue;
            }

            $likedN += (int) $likedOk;
            $improveN += (int) $improveOk;
            $reviewed = $num !== null && isset($precoded[$num]);
            $marks = $reviewed ? $precoded[$num] : $classifier->classify($liked, $improve);

            $list[] = [
                'l' => $likedOk ? mb_substr($liked, 0, self::MAX_ANSWER_LENGTH) : '',
                'i' => $improveOk ? mb_substr($improve, 0, self::MAX_ANSWER_LENGTH) : '',
                'c' => array_values(array_filter($codes, fn ($code) => ! empty($marks[$code]))),
                'r' => $reviewed,
            ];
        }

        return [
            'catalog' => array_map(fn ($t) => [$t[0], $t[1], $t[2]], $catalog),
            'answers' => $list,
            'liked_n' => $likedN,
            'improve_n' => $improveN,
        ];
    }

    private function value(array $row, string $header): mixed
    {
        foreach ($row as $key => $value) {
            if (Str::lower($key) === $header) {
                return $value;
            }
        }

        return null;
    }
}
