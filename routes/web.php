<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogCourseController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseGroupController;
use App\Http\Controllers\CourseGroupVersionController;
use App\Http\Controllers\CoursePageController;
use App\Http\Controllers\CourseReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProgressReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TestEnvironmentController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UserController;
use App\Models\CourseGroup;
use App\Models\User;
use App\Support\ProgramCatalog;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Públicas:   /, /cursos (catálogo), /<programa> (config mera.programs), /<carpeta>/<año>/<versión> (al final)
| auth:       /perfil (cualquier usuario autenticado)
| panel:      super admin | admin  → /panel, /usuarios, /categorias, /modulos, /carpetas
| super:      super admin          → /roles, /permisos, /historial, /versiones, /catalogo-cursos, /papelera
| URLs SIEMPRE en español (pedido del usuario 2026-10-20); los nombres de ruta siguen en inglés. Las
| URLs anteriores en inglés redirigen (301) al final del archivo. Excepción: /api/courses (contrato con
| el WordPress del catálogo; no se cambia).
*/

Route::redirect('/', '/iniciar-sesion')->name('home');

Route::get('/cursos', [CatalogController::class, 'index'])->name('catalog.index');

// Programas públicos multi-idioma (config/mera.php → programs): una URL por idioma,
// p. ej. /proceso-de-manipulacion-de-alimentos (es) y /food-handling-process (en)
Route::get('/{program}', [ProgramController::class, 'show'])
    ->whereIn('program', ProgramCatalog::slugs())
    ->name('programs.show');

$panelRoles = 'role:'.implode('|', User::PANEL_ROLES);

Route::middleware('auth')->group(function () use ($panelRoles) {
    // Perfil propio: cualquier usuario autenticado puede cambiar sus datos y contraseña.
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    // S25: sin auto-borrado de cuenta; la baja la hace un administrador desde Usuarios.

    Route::middleware($panelRoles)->group(function () {
        Route::get('/panel', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('usuarios', UserController::class)->except('show')->parameters(['usuarios' => 'user'])->names('users');
        Route::post('usuarios/{user}/invitar', [UserController::class, 'invite'])->middleware('throttle:10,1')->name('users.invite');
        Route::resource('categorias', CategoryController::class)->except('show')->parameters(['categorias' => 'category'])->names('categories');
        Route::resource('modulos', CourseController::class)->except('show')->parameters(['modulos' => 'course'])->names('courses');

        // Carpetas de curso (CLAUDE.md §10): Curso → Año → Versión por país → Módulos en orden
        // (docs/carpetas/INSTRUCTIVO_VERSIONES.md).
        Route::resource('carpetas', CourseGroupController::class)->parameters(['carpetas' => 'course_group'])->names('course-groups');
        Route::patch('carpetas/{courseGroup}/posicion', [CourseGroupController::class, 'move'])->name('course-groups.move');
        Route::prefix('carpetas/{courseGroup}/versiones')->name('course-groups.versions.')->scopeBindings()->group(function () {
            Route::post('/', [CourseGroupVersionController::class, 'store'])->name('store');
            Route::get('{version}', [CourseGroupVersionController::class, 'show'])->name('show');
            Route::get('{version}/editar', [CourseGroupVersionController::class, 'edit'])->name('edit');
            Route::put('{version}', [CourseGroupVersionController::class, 'update'])->name('update');
            Route::delete('{version}', [CourseGroupVersionController::class, 'destroy'])->name('destroy');
            Route::patch('{version}/publicar', [CourseGroupVersionController::class, 'publish'])->name('publish');
            Route::patch('{version}/orden', [CourseGroupVersionController::class, 'reorder'])->name('reorder');
        });

        // Reportes de curso por carpeta (docs/reportes/INSTRUCTIVO_REPORTES.md): super admin y admin.
        Route::prefix('carpetas/{courseGroup}/reportes')->name('course-groups.reports.')->scopeBindings()->group(function () {
            Route::get('nuevo', [CourseReportController::class, 'create'])->name('create');
            Route::post('/', [CourseReportController::class, 'store'])->middleware('throttle:10,1')->name('store');
            Route::get('{report}', [CourseReportController::class, 'show'])->name('show');
            Route::get('{report}/editar', [CourseReportController::class, 'edit'])->name('edit');
            Route::get('{report}/descargar', [CourseReportController::class, 'download'])->name('download');
            Route::put('{report}', [CourseReportController::class, 'update'])->middleware('throttle:10,1')->name('update');
            Route::delete('{report}', [CourseReportController::class, 'destroy'])->name('destroy');
            Route::patch('{report}/compartir', [CourseReportController::class, 'share'])->name('share');
            Route::get('{report}/pendientes', [CourseReportController::class, 'pending'])->name('pending');
            Route::patch('{report}/pendientes', [CourseReportController::class, 'classify'])->name('classify');
        });
        // Seguimiento anual por persona (docs/reportes/INSTRUCTIVO_REPORTE_PERSONAS.md): todos los cursos.
        Route::prefix('seguimiento')->name('progress.')->group(function () {
            Route::get('/', [ProgressReportController::class, 'index'])->name('index');
            Route::get('nuevo', [ProgressReportController::class, 'create'])->name('create');
            Route::post('/', [ProgressReportController::class, 'store'])->middleware('throttle:5,1')->name('store');
            Route::get('{report}', [ProgressReportController::class, 'show'])->name('show');
            Route::get('{report}/editar', [ProgressReportController::class, 'edit'])->name('edit');
            Route::put('{report}', [ProgressReportController::class, 'update'])->name('update');
            Route::get('{report}/personas', [ProgressReportController::class, 'people'])->name('people');
            Route::get('{report}/personas/descargar', [ProgressReportController::class, 'export'])->middleware('throttle:20,1')->name('export');
            Route::get('{report}/personas/{person}', [ProgressReportController::class, 'person'])->name('person');
            Route::post('{report}/encuestas', [ProgressReportController::class, 'storeSurvey'])->middleware('throttle:10,1')->name('surveys.store');
            Route::delete('{report}/encuestas/{survey}', [ProgressReportController::class, 'destroySurvey'])->name('surveys.destroy');
            Route::get('{report}/encuestas/{survey}/temas', [ProgressReportController::class, 'surveyTopics'])->name('surveys.topics');
            Route::patch('{report}/encuestas/{survey}/temas', [ProgressReportController::class, 'classifySurvey'])->name('surveys.classify');
            Route::get('{report}/descargar', [ProgressReportController::class, 'download'])->middleware('throttle:20,1')->name('download');
            Route::get('{report}/compartir', [ProgressReportController::class, 'shares'])->name('shares');
            Route::post('{report}/compartir', [ProgressReportController::class, 'storeShare'])->name('shares.store');
            Route::delete('{report}/compartir/{share}', [ProgressReportController::class, 'destroyShare'])->name('shares.destroy');
            Route::delete('{report}', [ProgressReportController::class, 'destroy'])->name('destroy');
        });

        Route::post('modulos/mover', [CourseGroupController::class, 'bulkMove'])->name('courses.bulk-move');
        Route::patch('modulos/{course}/posicion', [CourseGroupController::class, 'moveModule'])->name('courses.move');
        Route::delete('modulos/{course}/carpeta', [CourseGroupController::class, 'removeModule'])->name('courses.ungroup');

        // Solo fuera de producción (cintillo "Entorno de pruebas"): en producción responde 404.
        Route::post('entorno-pruebas/apagar-compartido', [TestEnvironmentController::class, 'unshare'])->name('test-environment.unshare');

        Route::middleware('role:'.User::ROLE_SUPER_ADMIN)->group(function () {
            Route::resource('roles', RoleController::class)->except('show');
            Route::resource('permisos', PermissionController::class)->except('show')->parameters(['permisos' => 'permission'])->names('permissions');
            Route::get('historial', [ActivityLogController::class, 'index'])->name('activity.logs.index');
            // Versiones por país (México, Latam, EUA…): catálogo editable (INSTRUCTIVO_VERSIONES.md §4).
            Route::resource('versiones', MarketController::class)->except('show')->parameters(['versiones' => 'market'])->names('markets');
            Route::patch('versiones/{market}/posicion', [MarketController::class, 'move'])->name('markets.move');
            // Catálogo de cursos de Capacitación (INSTRUCTIVO_REPORTE_PERSONAS.md §6): carga desde Excel y ajustes.
            Route::get('catalogo-cursos', [CatalogCourseController::class, 'index'])->name('course-catalog.index');
            Route::post('catalogo-cursos/cargar', [CatalogCourseController::class, 'import'])->middleware('throttle:10,1')->name('course-catalog.import');
            Route::get('catalogo-cursos/{course}/editar', [CatalogCourseController::class, 'edit'])->name('course-catalog.edit');
            Route::put('catalogo-cursos/{course}', [CatalogCourseController::class, 'update'])->name('course-catalog.update');
            // Papelera de módulos (F2): restaurar lo borrado mientras no se purgue.
            Route::get('papelera', [TrashController::class, 'index'])->name('trash.index');
            Route::post('papelera/{course}/restaurar', [TrashController::class, 'restore'])->withTrashed()->name('trash.restore');
        });
    });
});

// Reporte compartido sin sesión (link con token; solo agregados, noindex). Antes de la ruta comodín.
Route::get('reportes/compartido/{token}', [CourseReportController::class, 'shared'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:30,1')
    ->name('reports.shared');

// Seguimiento anual compartido sin sesión (§7.2.7): sin nombres, noindex; al desactivarlo, 404.
Route::get('seguimiento/compartido/{token}', [ProgressReportController::class, 'shared'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:60,1')
    ->name('progress.shared');

require __DIR__.'/auth.php';

// URLs anteriores en inglés → 301 a su equivalente en español (marcadores, links de correos ya
// enviados). Solo GET; antes de las páginas de carpeta para que ganen. Ver AppSupportegacypaths.
foreach (\App\Support\LegacyPaths::MAP as $old => $new) {
    Route::get($old.'/{rest?}', fn (\Illuminate\Http\Request $request, ?string $rest = null) => redirect(
        \App\Support\LegacyPaths::translate($new, $rest).($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301,
    ))->where('rest', '.*');
}

// Página pública de cada carpeta de curso, por versión (docs/carpetas/INSTRUCTIVO_VERSIONES.md §5).
// El link sigue la ruta del panel (Carpeta → Año → Versión):
//   /<carpeta>/<año>/<versión>       la página (p. ej. /higiene/2026/mexico). Es el link que se comparte
//   /<carpeta>/<año>                 301 a la versión por defecto (México) de ese año
//   /<carpeta>                       301 al año más reciente
//   /en/<carpeta> y /<carpeta>/en    formatos anteriores: 301 a la versión en inglés (EUA) más reciente
// SIEMPRE al final: cualquier ruta existente (login, cursos, dashboard…) se resuelve antes. Los slugs
// de carpetas no pueden ser rutas (CourseGroup::reservedSlugs()). Segmentos como texto (sin binding):
// las versiones se editan en el panel y la caché de rutas no las ve.
Route::get('/en/{group}', [CoursePageController::class, 'legacyEnglish'])
    ->where('group', CourseGroup::SLUG_PATTERN)
    ->name('course-pages.legacy-en');
Route::get('/{group}/en', [CoursePageController::class, 'legacyEnglish'])
    ->where('group', CourseGroup::SLUG_PATTERN)
    ->name('course-pages.legacy-en.suffix');
Route::get('/{group}/{year}/{market}', [CoursePageController::class, 'version'])
    ->where(['group' => CourseGroup::SLUG_PATTERN, 'year' => '20\d{2}', 'market' => CourseGroup::SLUG_PATTERN])
    ->name('course-pages.version');
Route::get('/{group}/{year}', [CoursePageController::class, 'year'])
    ->where(['group' => CourseGroup::SLUG_PATTERN, 'year' => '20\d{2}'])
    ->name('course-pages.year');
Route::get('/{courseGroup}', [CoursePageController::class, 'show'])
    ->where('courseGroup', CourseGroup::SLUG_PATTERN)
    ->name('course-pages.show');
