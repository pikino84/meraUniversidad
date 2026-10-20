<?php

/*
|--------------------------------------------------------------------------
| Configuración propia de Mera Universidad
|--------------------------------------------------------------------------
*/

return [

    // Categorías (2026-10-14): OCULTAS en el panel y en el catálogo /cursos mientras Capacitación
    // decide si las quiere y cómo (se preguntará por correo; CLAUDE.md §6). Los datos y la API
    // /api/courses no cambian (el WordPress muestra el nombre de la categoría).
    'categories_enabled' => (bool) env('MERA_CATEGORIES_ENABLED', false),

    // Módulos por página en el catálogo público /cursos.
    'catalog_per_page' => (int) env('MERA_CATALOG_PER_PAGE', 12),

    // Las fechas se GUARDAN en UTC (config/app.php) y se MUESTRAN en esta zona.
    // No cambiar app.timezone: los registros existentes están en UTC y se mezclarían.
    'display_timezone' => env('MERA_DISPLAY_TIMEZONE', 'America/Mexico_City'),

    'courses' => [

        // Raíz física del disco público. Las rutas guardadas en BD (courses.path,
        // courses.cover_image) son relativas a esta carpeta, p. ej. "cursos/curso-AbC123".
        'storage_root' => env('MERA_COURSES_STORAGE_ROOT', storage_path('app/public')),

        // Subcarpeta (dentro de storage_root) donde viven los cursos.
        'directory' => 'cursos',

        // Carpeta PRIVADA para extracciones temporales (no accesible por web).
        'temp_root' => env('MERA_COURSES_TEMP_ROOT', storage_path('app/tmp/course-uploads')),

        // Papelera: los cursos eliminados se mueven aquí y se purgan pasado N días.
        'trash_root' => env('MERA_COURSES_TRASH_ROOT', storage_path('app/trash/courses')),
        'trash_retention_days' => (int) env('MERA_COURSES_TRASH_DAYS', 30),

        // URL base desde la que se sirven los cursos. Si es null se usa asset('storage').
        // Recomendado en producción: un subdominio aparte (p. ej. https://contenido.dominio.com)
        // para que el JS de los cursos NO corra en el mismo origen que el panel admin.
        'base_url' => env('MERA_COURSES_URL'),

        // Límites del paquete ZIP.
        'max_zip_kb' => (int) env('MERA_COURSES_MAX_ZIP_KB', 1024000),               // ~1 GB comprimido
        'max_uncompressed_mb' => (int) env('MERA_COURSES_MAX_UNCOMPRESSED_MB', 3072), // 3 GB descomprimido
        'max_files' => (int) env('MERA_COURSES_MAX_FILES', 5000),

        // Portada.
        'cover_max_kb' => 2048,
        'cover_mimes' => ['jpg', 'jpeg', 'png', 'webp'],

        // Archivo de entrada obligatorio del paquete.
        'entry_file' => 'index.html',

        // Lista blanca de extensiones permitidas dentro del ZIP.
        // Cualquier otra (php, phtml, phar, htaccess, exe, sh…) hace que el ZIP se rechace.
        'allowed_extensions' => [
            // Web
            'html', 'htm', 'css', 'js', 'mjs', 'json', 'map', 'xml', 'xsd', 'dtd', 'txt', 'csv', 'md',
            // Imágenes
            'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'bmp', 'ico',
            // Fuentes
            'woff', 'woff2', 'ttf', 'otf', 'eot',
            // Audio / video / subtítulos
            'mp4', 'm4v', 'webm', 'ogv', 'ogg', 'mp3', 'm4a', 'aac', 'wav', 'vtt', 'srt',
            // Documentos descargables
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'xlsm', 'ppt', 'pptx',
        ],

        // Entradas basura que se ignoran silenciosamente (exportes desde macOS/Windows).
        'ignored_entries' => ['__MACOSX/', '.DS_Store', 'Thumbs.db', 'desktop.ini'],

        'per_page' => 20,
    ],

    'api' => [
        // Minutos que se cachea el listado público de cursos.
        'courses_cache_minutes' => (int) env('MERA_API_COURSES_CACHE_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Programas: páginas públicas que agrupan varios módulos en un recorrido
    |--------------------------------------------------------------------------
    | Cada programa puede tener varias versiones de idioma (es / en), cada una con SU PROPIA URL
    | pública (cursos.meracorporation.com/<slug>) y sus módulos. Un idioma sin módulos NO se
    | publica (su URL da 404 y no aparece en el selector de idioma).
    |
    | Textos fijos de la página (botones, pasos, consejos): lang/<idioma>/program.php.
    | Textos propios del programa (título, descripción, títulos de módulos): aquí, por idioma.
    |
    | Módulos de un idioma, dos formas (la primera que exista gana):
    |   'modules'      → lista explícita: cada módulo se resuelve por el SLUG del curso en la BD
    |                    (re-subir un ZIP no rompe el enlace); "fallback_path" solo si el curso no
    |                    existe en la BD; "start" = ancla opcional de inicio (isEazy: "#/slide/…").
    |   'course_group' → slug de una carpeta de curso: toma los módulos de una de sus VERSIONES, en
    |                    orden. 'version' => ['market' => 'LATAM', 'year' => 2026] la elige; sin
    |                    'market', la versión por defecto del idioma (es → mera.versions.default_market,
    |                    en → mera.versions.english_market); sin 'year', el año más reciente.
    |
    | Evaluación y encuesta: 'url' puede ser un texto (igual para todos los idiomas) o un arreglo
    | por idioma. Un idioma sin URL propia usa la del idioma por defecto.
    */
    'programs' => [

        'proceso-de-manipulacion-de-alimentos' => [
            'default_language' => 'es',

            'languages' => [
                'es' => [
                    'slug' => 'proceso-de-manipulacion-de-alimentos',
                    'eyebrow' => 'Curso en línea',
                    'title' => 'Proceso de manipulación de alimentos',
                    'lead' => 'Aprende paso a paso cómo cuidar los alimentos que servimos. Hacer las cosas bien es parte de lo que somos.',
                    'description' => 'Curso en línea de MERA: 5 módulos sobre el manejo higiénico de alimentos, con evaluación final.',
                    // Carpeta de curso que alimenta esta página (la crea `php artisan mera:program-folder`).
                    // Si la carpeta tiene módulos en español, se usan esos y en su orden; la lista de
                    // abajo queda como RESPALDO y aporta el título corto y el ancla de inicio por slug.
                    'course_group' => 'manipulacion-de-alimentos',
                    'version' => ['market' => 'LATAM'], // sus 5 módulos son la versión Latam
                    'modules' => [
                        ['title' => 'Recepción y almacenamiento operativo', 'course_slug' => 'modulo-1-manejo-higienico-latam', 'fallback_path' => 'cursos/modulo-1-manejo-higienico-latam', 'start' => '#/slide/saz3GISUgN'],
                        ['title' => 'Proceso en cocina: del congelador a la mesa', 'course_slug' => 'modulo-2-manejo-higienico-latam', 'fallback_path' => 'cursos/modulo-2-manejo-higienico-latam', 'start' => '#/slide/WL8kWAa7Y'],
                        ['title' => 'Control químico y verificación de la desinfección', 'course_slug' => 'modulo-3-manejo-higienico-latam', 'fallback_path' => 'cursos/modulo-3-manejo-higienico-latam', 'start' => '#/slide/DNs52HQKF'],
                        ['title' => 'Normativa de seguridad, agua y químicos', 'course_slug' => 'modulo-4-manejo-higienico-latam', 'fallback_path' => 'cursos/modulo-4-manejo-higienico-latam', 'start' => '#/slide/8BnH6wHF8'],
                        ['title' => 'Exclusión de plagas y manejo integral de residuos', 'course_slug' => 'modulo-5-manejo-higienico-latam', 'fallback_path' => 'cursos/modulo-5-manejo-higienico-latam', 'start' => '#/slide/g5SbzQZUF'],
                    ],
                ],

                // Versión en inglés: se publica sola en /food-handling-process en cuanto la carpeta
                // tenga módulos en la versión EUA (mientras tanto da 404).
                'en' => [
                    'slug' => 'food-handling-process',
                    'eyebrow' => 'Online course',
                    'title' => 'Food handling process',
                    'lead' => 'Learn step by step how to take care of the food we serve. Doing things right is part of who we are.',
                    'description' => 'MERA online course: 5 modules on hygienic food handling, with a final assessment.',
                    'course_group' => 'manipulacion-de-alimentos',
                    'modules' => [],
                ],
            ],

            'actions' => [
                // RESPALDO: si la carpeta tiene links de evaluación/encuesta ("Editar carpeta"), mandan esos.
                // Por ahora ambos idiomas usan los mismos formularios.
                ['type' => 'evaluation', 'url' => ['es' => 'https://forms.cloud.microsoft/r/3e12BETYuE?origin=lprLink', 'en' => null]],
                ['type' => 'survey', 'url' => ['es' => 'https://forms.cloud.microsoft/r/6qpNWit2U3?origin=lprLink', 'en' => null]],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reportes de curso por carpeta (docs/reportes/INSTRUCTIVO_REPORTES.md §5-§6)
    |--------------------------------------------------------------------------
    | Reglas generales de agrupación, IDÉNTICAS al script de referencia (generar_reporte.py).
    | La definición de cada encuesta (preguntas favorables y temas) está en config/report_surveys.php.
    */
    /*
    | Carpetas por año y versión por país (docs/carpetas/INSTRUCTIVO_VERSIONES.md).
    | Las versiones (México, Latam, EUA…) se editan en el panel; aquí solo los valores por defecto.
    */
    /*
    | Fuera de producción (QA en university.meracorporation.com y local) todas las páginas llevan
    | el cintillo "Entorno de pruebas" y el panel ofrece "Apagar todo lo compartido"
    | (pedido del usuario 2026-10-19: Capacitación subía material real a QA).
    */
    'test_environment' => [
        'production_url' => env('MERA_PRODUCTION_URL', 'https://cursos.meracorporation.com'),
    ],

    'versions' => [
        // Versión que muestra /<carpeta> (sin versión en la URL).
        'default_market' => env('MERA_DEFAULT_MARKET', 'MX'),

        // Antes de las versiones, la página pública guardaba el avance como carpeta:<slug>:<idioma>.
        // La versión que heredó cada idioma lee esa clave para no perder el avance.
        'legacy_language_markets' => ['es' => 'MX', 'en' => 'US'],

        // Página en inglés anterior (/en/<carpeta> y /<carpeta>/en): 301 a esta versión.
        'english_market' => 'US',
    ],

    /*
    | Seguimiento anual por persona (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md).
    */
    'progress' => [
        // Cortes que se conservan (los más recientes).
        'keep_reports' => (int) env('MERA_PROGRESS_KEEP_REPORTS', 6),

        // Solo cuentan para el seguimiento los cursos de este año en adelante (Capacitación, 2026-10-19:
        // los de 2025 "son del año pasado, no los vamos a tomar en cuenta"). Al cargar el catálogo, los
        // anteriores se crean con "No cuenta"; se puede cambiar por curso en el panel.
        'tracking_from_year' => (int) env('MERA_PROGRESS_FROM_YEAR', 2026),

        // Calificación mínima para aprobar (cada curso del catálogo puede tener la suya).
        'passing_grade' => 80,

        // Valores al CREAR un curso del catálogo (respuestas de Capacitación, 2026-10-19). Después se
        // editan en el panel y la importación no los cambia.
        'catalog_defaults' => [
            'NEG-OPE-007-EL-26-MX' => ['passing_grade' => 90],    // Corresponsal Cambiario
            'NEG-CUL-004-EL-26' => ['has_evaluation' => false],   // Inducción a la Empresa
        ],

        // Tablero: ranking con localidades de al menos N personas; "dónde reprueban más" con al menos
        // N evaluaciones.
        'dashboard' => [
            'ranking_min_people' => 10,
            'failing_min_graded' => 20,
        ],

        // Personas por página en la consulta de colaboradores.
        'people_per_page' => 25,

        // Excel del catálogo: tamaño máximo (KB) y filas donde se buscan los encabezados.
        'catalog_max_upload_kb' => 5120,
        'catalog_header_scan' => 10,
    ],

    'reports' => [
        // Tamaño máximo de CADA uno de los 3 Excel (KB).
        'max_upload_kb' => (int) env('MERA_REPORTS_MAX_UPLOAD_KB', 30720),

        // Extracción temporal PRIVADA de los Excel; se borran siempre al terminar.
        'temp_root' => storage_path('app/tmp/report-uploads'),

        // Unidad de negocio (reporte de personal) → localidad que se muestra.
        'localities' => [
            'Panama' => 'Panamá', 'Bajio' => 'Bajío', 'Ciudad Juarez' => 'Ciudad Juárez', 'Torreon' => 'Torreón',
            'Cancun Radio' => 'Cancún Radio', 'Cancún T2 Abt2' => 'Cancún T2',
            'MERA Bogota' => 'Colombia', 'MERA Medellin' => 'Colombia', 'MDE GUACAMOLE GRILL' => 'Colombia',
        ],

        // Unidades que no entran al reporte (personal de Presidencia; tampoco en el reporte base).
        'excluded_localities' => ['Cancun C4'],

        // Sin unidad de negocio: Colombia si el departamento o el puesto es de LATAM; si no, se excluye.
        'colombia_without_unit' => '/LATAM|\bBOG\b|\bMED\b/i',
        'colombia' => 'Colombia',

        // Departamento → área. Lo que no está aquí va a 'area_other'.
        'areas' => [
            'Piso' => 'Piso / Restaurantes', 'Restaurantes' => 'Piso / Restaurantes',
            'Cocina' => 'Cocina', 'Bar' => 'Bar', 'Almacen' => 'Almacén',
            'Comisariato Foodcourt' => 'Comisariato', 'Comisariato Food Court PB' => 'Comisariato',
            'Mantenimiento' => 'Mantenimiento y limpieza', 'Limpieza' => 'Mantenimiento y limpieza',
        ],
        'area_other' => 'Corporativo y administrativo / otros',

        // Localidad y área de quien ya no está activo (o no está en el reporte de personal).
        'inactive' => 'Ya no activo',

        // Encuesta que usan las carpetas si no se indica otra (config/report_surveys.php).
        'default_survey' => 'higiene',

        // Umbrales del tablero (INSTRUCTIVO_REPORTES.md §6.5): los mismos de la plantilla de
        // referencia. Un solo lugar para el JS (report-dashboard.js) y los textos de la vista.
        // "Reprobar" = menos de 80 NO es configurable: las franjas b80/b70/b60/b50 se calculan con
        // ese corte en CourseReportBuilder y son parte del contrato de datos (§7).
        'dashboard' => [
            'podium_min_people' => 20,       // podio y ranking: localidades con al menos N asignados
            'ranking_low_from' => 13,        // del lugar N en adelante: "aún sin cumplimiento"
            'failing_group_min' => 20,       // "dónde reprueban más": grupos con al menos N evaluados
            'position_min_graded' => 15,     // puestos con al menos N evaluados
            'ok_below' => 0.35,              // va bien: menos de este % sacó menos de 80
            'needs_support_from' => 0.45,    // necesita apoyo: este % o más
            'satisfaction_min_answers' => 30, // satisfacción por localidad: al menos N respuestas
        ],
    ],

];
