<?php

/*
|--------------------------------------------------------------------------
| Encuestas de satisfacción de los reportes de curso ("seeder por curso")
|--------------------------------------------------------------------------
| docs/reportes/INSTRUCTIVO_REPORTES.md §4 y §6.4. Cada carpeta de curso usa una de estas
| definiciones (por defecto config('mera.reports.default_survey')). Para otro curso: copiar el
| bloque, ajustar las preguntas favorables y su catálogo de temas.
|
| - favorable: [clave, fragmento del encabezado de la pregunta, respuestas favorables]. La
|   respuesta se compara TAL CUAL (solo sin "*" y con espacios colapsados), como el script.
| - catalog: temas de "lo que más valoran" (v..) y "qué piden mejorar" (m..), en el orden del
|   CSV original. keywords: fragmentos (sin acentos, en minúsculas) para proponer temas a las
|   respuestas NUEVAS; un admin corrige la propuesta en pantalla.
| - precoded_file: CSV privado (storage/, fuera de git) con respuestas ya clasificadas por
|   No. de empleado. Tiene prioridad sobre las palabras clave.
*/

return [

    'higiene' => [
        'name' => 'Introducción a la Higiene y Procedimientos',

        'favorable' => [
            ['f_utilidad', 'temas tratados son útiles', ['Muy de acuerdo', 'De acuerdo']],
            ['f_riesgos', 'riesgos invisibles', ['Sí']],
            ['f_ejemplos', 'ejemplos y casos presentados', ['Muy claros y aplicables']],
            ['f_herramientas', 'herramientas prácticas', ['Sí, muchas']],
            ['f_capaz', 'capaz de aplicar', ['Sí']],
            ['f_facil', 'fácil fue acceder', ['Muy fácil', 'Fácil']],
            ['f_recursos', 'Los recursos', ['Muy útiles', 'Útiles']],
            ['f_recomienda', 'Recomendarías este curso', ['Si', 'Sí']],
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

];
