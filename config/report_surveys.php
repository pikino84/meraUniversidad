<?php

/*
|--------------------------------------------------------------------------
| Encuestas de satisfacción de los reportes de curso ("seeder por curso")
|--------------------------------------------------------------------------
| docs/reportes/INSTRUCTIVO_REPORTES.md §4 y §6.4. Cada carpeta de curso usa una de estas
| definiciones. Al crear un corte, "Automática" elige la del curso de la versión (course_codes), luego
| la del título del cuestionario (title) y, si ninguna coincide, "general". Para otro curso: copiar
| el bloque, ajustar las preguntas favorables y (opcional) su catálogo de temas.
|
| - favorable: [clave, fragmento del encabezado de la pregunta, respuestas favorables, etiqueta en el
|   tablero (null = cuenta para la satisfacción, sin barra propia)]. La respuesta se compara TAL CUAL
|   (solo sin "*" y con espacios colapsados), como el script. Una pregunta que la encuesta no trae se
|   omite con aviso: la satisfacción se calcula con las demás (antes detenía el corte, 2026-10-21).
|   Respuestas favorables = 'yes' para un Sí/No escrito a mano (SurveyAnswerClassifier::yesNo: "si",
|   "claro", "por supuesto"… = sí; "no" = no; lo demás queda fuera del %).
| - liked_extra_question (opcional): otra pregunta abierta de "lo que valoran"; se suma a la principal.
| - title: fragmento de la columna "Cuestionario" que identifica la encuesta (sin acentos ni mayúsculas).
| - catalog (opcional): sin él se usa el catálogo base de temas (config mera.progress.survey_topics).
| - catalog: temas de "lo que más valoran" (v..) y "qué piden mejorar" (m..), en el orden del
|   CSV original. keywords: fragmentos (sin acentos, en minúsculas) para proponer temas a las
|   respuestas NUEVAS; un admin corrige la propuesta en pantalla.
| - course_codes: cursos del catálogo que usan la encuesta en el seguimiento anual (opcional).
| - precoded_file: CSV privado (storage/, fuera de git) con respuestas ya clasificadas por
|   No. de empleado. Tiene prioridad sobre las palabras clave.
*/

return [

    'higiene' => [
        'name' => 'Introducción a la Higiene y Procedimientos',
        'title' => 'Higiene',

        // Seguimiento anual (INSTRUCTIVO_REPORTE_PERSONAS.md §6.1): cursos del catálogo que usan esta
        // configuración para calcular la satisfacción. Sin configuración, la encuesta muestra los
        // indicadores comunes (recomienda, NPS, problemas técnicos) y la distribución de cada pregunta.
        'course_codes' => ['NRM-HIG-002-EL-26'],

        'favorable' => [
            ['f_utilidad', 'temas tratados son útiles', ['Muy de acuerdo', 'De acuerdo'], 'Les sirve para su trabajo'],
            ['f_riesgos', 'riesgos invisibles', ['Sí'], 'Entienden los riesgos'],
            ['f_ejemplos', 'ejemplos y casos presentados', ['Muy claros y aplicables'], 'Ejemplos claros'],
            ['f_herramientas', 'herramientas prácticas', ['Sí, muchas'], 'Aprendieron herramientas'],
            ['f_capaz', 'capaz de aplicar', ['Sí'], 'Se sienten capaces de aplicarlo'],
            ['f_facil', 'fácil fue acceder', ['Muy fácil', 'Fácil'], 'Fue fácil de usar'],
            ['f_recursos', 'Los recursos', ['Muy útiles', 'Útiles'], 'Recursos útiles'],
            ['f_recomienda', 'Recomendarías este curso', ['Si', 'Sí'], null],
        ],

        'liked_question' => 'más te gustó',
        'improve_question' => 'Qué mejorarías',

        'precoded_file' => storage_path('app/reportes/codigos/higiene.csv'),

        'catalog' => [
            ['m01', 'mejora', 'Video de lavado de manos: revisar que el procedimiento sea correcto', ['video de lavado', 'lavado de manos', 'lavar las manos', 'lavarse las manos']],
            ['m02', 'mejora', 'Termómetro: explicar mejor cómo se calibra y se usa', ['termometro', 'calibr']],
            ['m03', 'mejora', 'Evaluación: cambiar las preguntas, son las mismas del año pasado', ['mismas preguntas', 'ano pasado', 'se repiten', 'repetidas']],
            ['m04', 'mejora', 'Evaluación: preguntas más claras y específicas', ['preguntas mas claras', 'confus', 'ambigu', 'mal redactad']],
            ['m05', 'mejora', 'Evaluación: que muestre qué respuestas se fallaron', ['en que me equivoque', 'respuestas incorrectas', 'respuestas correctas', 'que falle', 'mis errores']],
            ['m06', 'mejora', 'Nuevo curso sobre manejo de químicos', ['quimico']],
            ['m07', 'mejora', 'Más ejemplos y casos reales (bar, imágenes, temperaturas)', ['mas ejemplos', 'casos reales', 'mas imagenes', 'temperatura']],
            ['m08', 'mejora', 'Curso más corto o con menos módulos', ['muy largo', 'mas corto', 'menos modulos', 'extenso', 'tardado']],
            ['m09', 'mejora', 'Adaptar el curso al área o puesto (almacén, administrativo, bar)', ['mi area', 'mi puesto', 'por area', 'por puesto', 'administrativ']],
            ['m10', 'mejora', 'Juegos y actividades: letra más grande y clara', ['letra', 'tamano']],
            ['m11', 'mejora', 'Videos y ejercicios más dinámicos y prácticos', ['dinamic', 'interactiv', 'mas practic', 'aburrid']],
            ['m12', 'mejora', 'Más cursos y seguimiento para reforzar lo aprendido', ['mas cursos', 'seguimiento', 'reforz', 'mas seguido']],
            ['v01', 'valora', 'Explicaciones claras y fáciles de entender', ['clar', 'facil de entender', 'entendible', 'explica']],
            ['v02', 'valora', 'Videos, animaciones y apoyos visuales', ['video', 'animacion', 'visual', 'imagen']],
            ['v03', 'valora', 'Ejemplos y casos', ['ejemplo', 'caso']],
            ['v04', 'valora', 'Actividades, juegos y retroalimentación', ['actividad', 'juego', 'dinamica', 'retroaliment', 'interactiv']],
            ['v05', 'valora', 'Contaminación cruzada y riesgos invisibles', ['contaminacion', 'cruzada', 'riesgo', 'bacteria']],
            ['v06', 'valora', 'Higiene personal y lavado de manos', ['higiene personal', 'lavado de manos', 'lavarse las manos', 'aseo']],
            ['v07', 'valora', 'Manejo de alimentos, etiquetado y almacenamiento (PEPS, refrigeración, termómetro)', ['etiquet', 'almacenamiento', 'peps', 'refriger', 'termometro', 'manejo de alimentos']],
            ['v08', 'valora', 'Útil y aplicable al trabajo y a la vida diaria', ['util', 'aplic', 'vida diaria', 'en mi trabajo', 'en casa']],
            ['v09', 'valora', 'Aprendizaje y refresco de conocimientos', ['aprend', 'refresc', 'record', 'conocimiento']],
            ['v_todo', 'valora', 'Todo en general', []],
        ],
    ],

    // Misma encuesta que Higiene salvo una pregunta (2026-10-21, pedido de Tania). Sin clasificación
    // previa ni catálogo propio: los comentarios se proponen con el catálogo base y se corrigen en pantalla.
    'seguridad' => [
        'name' => 'Fundamentos de Seguridad',
        'title' => 'Fundamentos de Seguridad',
        'course_codes' => ['NRM-SEG-002-EL-26'],

        'favorable' => [
            ['f_utilidad', 'temas tratados son útiles', ['Muy de acuerdo', 'De acuerdo'], 'Les sirve para su trabajo'],
            ['f_fundamentos', 'fundamentos de Seguridad', ['Sí'], 'Entienden los fundamentos'],
            ['f_ejemplos', 'ejemplos y casos presentados', ['Muy claros y aplicables'], 'Ejemplos claros'],
            ['f_herramientas', 'herramientas prácticas', ['Sí, muchas'], 'Aprendieron herramientas'],
            ['f_capaz', 'capaz de aplicar', ['Sí'], 'Se sienten capaces de aplicarlo'],
            ['f_facil', 'fácil fue acceder', ['Muy fácil', 'Fácil'], 'Fue fácil de usar'],
            ['f_recursos', 'Los recursos', ['Muy útiles', 'Útiles'], 'Recursos útiles'],
            ['f_recomienda', 'Recomendarías este curso', ['Si', 'Sí'], null],
        ],

        'liked_question' => 'más te gustó',
        'improve_question' => 'Qué mejorarías',
    ],

    // Hostigamiento y Acoso 2026 (2026-10-22): la encuesta se llama «Respeto que Construye», no trae
    // la escala 0–10 (trae «¿Cómo calificarías el curso en general?») y «Recomendarías» es abierta.
    'respeto' => [
        'name' => 'Respeto que Construye (Hostigamiento y Acoso)',
        'title' => 'Respeto que Construye',
        'course_codes' => ['NRM-SEG-004-EL-26'],

        'favorable' => [
            ['f_utilidad', 'temas tratados son útiles', ['Muy de acuerdo', 'De acuerdo'], 'Les sirve para su trabajo'],
            ['f_respeto', 'fomentar el respeto', ['Sí, completamente'], 'Saben fomentar el respeto'],
            ['f_ejemplos', 'ejemplos y casos presentados', ['Muy claros y aplicables'], 'Ejemplos claros'],
            ['f_herramientas', 'herramientas prácticas', ['Sí, muchas'], 'Aprendieron herramientas'],
            ['f_capaz', 'capaz de aplicar', ['Si', 'Sí'], 'Se sienten capaces de aplicarlo'],
            ['f_facil', 'fácil fue acceder', ['Muy fácil', 'Fácil'], 'Fue fácil de usar'],
            ['f_recursos', 'Los recursos', ['Muy útiles', 'Útiles'], 'Recursos útiles'],
            ['f_recomienda', 'Recomendarías', 'yes', null],
        ],

        'liked_question' => 'te gustó más',
        'liked_extra_question' => 'más útil o impactante',
        'improve_question' => 'podríamos mejorar',
    ],

    // Cualquier otro curso: solo lo que traen todas las encuestas de MERA. Satisfacción = % que
    // recomienda el curso (cerrada o escrita a mano). Va al final: es el respaldo de "Automática".
    'general' => [
        'name' => 'General (cualquier curso)',

        'favorable' => [
            ['f_recomienda', 'Recomendarías', 'yes', 'Recomiendan el curso'],
        ],
    ],

];
