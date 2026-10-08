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

];
