<?php

namespace App\Services\Reports;

/**
 * Comentarios abiertos de la encuesta → temas del catálogo (INSTRUCTIVO_REPORTES.md §6.4).
 *
 * Solo PROPONE temas para las respuestas que no vienen ya clasificadas: busca las palabras clave
 * del catálogo (config/report_surveys.php) en el texto sin acentos y en minúsculas. Un admin
 * revisa y corrige la propuesta en pantalla ("Pendientes de clasificar").
 *
 *  - "¿Qué te gustó?" → temas v.. ; v_respondio = 1 si dice algo real; v_todo = 1 si dice "todo".
 *  - "¿Qué mejorarías?" → temas m.. ; "nada", "todo bien"… no cuentan como pedido de mejora.
 */
class SurveyAnswerClassifier
{
    /** Respuestas que no dicen nada (después de quitar signos). */
    private const EMPTY_ANSWERS = ['', 'nada', 'ninguno', 'ninguna', 'na', 'n a', 'no', 'ningun', 'nada en particular', 'sin comentarios', 'ningun comentario'];

    /** "Qué mejorarías" que en realidad dicen que no hay nada que mejorar. */
    private const NOTHING_TO_IMPROVE = ['todo bien', 'todo esta bien', 'nada todo bien', 'todo excelente', 'todo perfecto', 'esta bien asi', 'asi esta bien', 'nada esta bien', 'nada me gusto todo'];

    /** @param list<array{0:string,1:string,2:string,3?:list<string>}> $catalog */
    public function __construct(private readonly array $catalog) {}

    /**
     * @return array<string,int> código del catálogo => 0/1, más 'v_respondio'
     */
    public function classify(mixed $liked, mixed $improve): array
    {
        $codes = [];
        foreach ($this->catalog as $topic) {
            $codes[$topic[0]] = 0;
        }
        $codes['v_respondio'] = 0;

        $likedText = self::plain($liked);
        $improveText = self::plain($improve);

        if (! in_array($likedText, self::EMPTY_ANSWERS, true)) {
            $codes['v_respondio'] = 1;

            if (array_key_exists('v_todo', $codes) && preg_match('/^todo( (bien|en general|me gusto|me gusto mucho|excelente))?$/', $likedText)) {
                $codes['v_todo'] = 1;
            } else {
                $this->match($likedText, 'valora', $codes);
            }
        }

        if (! in_array($improveText, self::EMPTY_ANSWERS, true) && ! in_array($improveText, self::NOTHING_TO_IMPROVE, true)) {
            $this->match($improveText, 'mejora', $codes);
        }

        return $codes;
    }

    /** Texto comparable: sin acentos, minúsculas, solo letras/números y espacios simples. */
    public static function plain(mixed $text): string
    {
        $normalized = ReportWorkbookReader::normalize(ReportWorkbookReader::clean($text));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', $normalized)));
    }

    /** @param array<string,int> $codes */
    private function match(string $text, string $type, array &$codes): void
    {
        foreach ($this->catalog as $topic) {
            if ($topic[1] !== $type || $topic[0] === 'v_todo') {
                continue;
            }

            foreach ($topic[3] ?? [] as $keyword) {
                if ($keyword !== '' && str_contains($text, self::plain($keyword))) {
                    $codes[$topic[0]] = 1;
                    break;
                }
            }
        }
    }
}
