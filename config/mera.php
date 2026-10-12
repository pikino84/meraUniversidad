<?php

/*
|--------------------------------------------------------------------------
| Configuración propia de Mera Universidad
|--------------------------------------------------------------------------
*/

return [

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
    | Programas: páginas públicas que agrupan varios cursos en un recorrido
    |--------------------------------------------------------------------------
    | La clave es la URL pública (cursos.meracorporation.com/<clave>).
    | Cada módulo se resuelve por el SLUG del curso en la BD: si se vuelve a subir el ZIP
    | (carpeta nueva), el enlace sigue funcionando. "fallback_path" solo se usa si el curso
    | no existe en la BD (p. ej. en un entorno local sin esos registros).
    | "start" es el ancla opcional de inicio dentro del curso (Articulate: "#/slide/…").
    | Para agregar otro programa basta con otra entrada aquí (no requiere código nuevo).
    */
    'programs' => [

        'proceso-de-manipulacion-de-alimentos' => [
            'title' => 'Proceso de manipulación de alimentos',
            'eyebrow' => 'Curso en línea',
            'lead' => 'Aprende paso a paso cómo cuidar los alimentos que servimos. Hacer las cosas bien es parte de lo que somos.',
            'description' => 'Curso en línea de MERA: 5 módulos sobre el manejo higiénico de alimentos, con evaluación final.',
            'modules' => [
                ['title' => 'Recepción y almacenamiento operativo', 'course_slug' => 'modulo-1-manejo-higienico-latam', 'fallback_path' => 'cursos/modulo-1-manejo-higienico-latam', 'start' => '#/slide/saz3GISUgN'],
                ['title' => 'Proceso en cocina: del congelador a la mesa', 'course_slug' => 'modulo-2-manejo-higienico-latam', 'fallback_path' => 'cursos/modulo-2-manejo-higienico-latam', 'start' => '#/slide/WL8kWAa7Y'],
                ['title' => 'Control químico y verificación de la desinfección', 'course_slug' => 'modulo-3-manejo-higienico-latam', 'fallback_path' => 'cursos/modulo-3-manejo-higienico-latam', 'start' => '#/slide/DNs52HQKF'],
                ['title' => 'Normativa de seguridad, agua y químicos', 'course_slug' => 'modulo-4-manejo-higienico-latam', 'fallback_path' => 'cursos/modulo-4-manejo-higienico-latam', 'start' => '#/slide/8BnH6wHF8'],
                ['title' => 'Exclusión de plagas y manejo integral de residuos', 'course_slug' => 'modulo-5-manejo-higienico-latam', 'fallback_path' => 'cursos/modulo-5-manejo-higienico-latam', 'start' => '#/slide/g5SbzQZUF'],
            ],
            'actions' => [
                ['type' => 'evaluation', 'title' => 'Evaluación', 'text' => 'Muestra lo que aprendiste. Contesta con calma.', 'button' => 'Hacer evaluación', 'url' => 'https://forms.cloud.microsoft/r/3e12BETYuE?origin=lprLink'],
                ['type' => 'survey', 'title' => 'Encuesta', 'text' => 'Cuéntanos qué te pareció el curso. Tu opinión nos ayuda a mejorar.', 'button' => 'Contestar encuesta', 'url' => 'https://forms.cloud.microsoft/r/6qpNWit2U3?origin=lprLink'],
            ],
            'values' => ['Pasión', 'Integridad', 'Excelencia', 'Respeto'],
        ],

    ],

];
