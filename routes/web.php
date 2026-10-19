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
| Públicas:   /, /cursos (catálogo), /<programa> (config mera.programs), /<carpeta>[/en] (al final)
| auth:       perfil propio (cualquier usuario autenticado)
| panel:      super admin | admin  → dashboard, usuarios, categorías, cursos, carpetas de curso
| super:      super admin          → roles, permisos, historial
*/

Route::redirect('/', '/login')->name('home');

Route::get('/cursos', [CatalogController::class, 'index'])->name('catalog.index');

// Programas públicos multi-idioma (config/mera.php → programs): una URL por idioma,
// p. ej. /proceso-de-manipulacion-de-alimentos (es) y /food-handling-process (en)
Route::get('/{program}', [ProgramController::class, 'show'])
    ->whereIn('program', ProgramCatalog::slugs())
    ->name('programs.show');

$panelRoles = 'role:'.implode('|', User::PANEL_ROLES);

Route::middleware('auth')->group(function () use ($panelRoles) {
    // Perfil propio: cualquier usuario autenticado puede cambiar sus datos y contraseña.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // S25: sin auto-borrado de cuenta; la baja la hace un administrador desde Usuarios.

    Route::middleware($panelRoles)->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('users', UserController::class)->except('show');
        Route::post('users/{user}/invite', [UserController::class, 'invite'])->middleware('throttle:10,1')->name('users.invite');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('courses', CourseController::class)->except('show');

        // Carpetas de curso (CLAUDE.md §10): Curso → Año → Versión por país → Módulos en orden
        // (docs/carpetas/INSTRUCTIVO_VERSIONES.md).
        Route::resource('course-groups', CourseGroupController::class);
        Route::patch('course-groups/{courseGroup}/move', [CourseGroupController::class, 'move'])->name('course-groups.move');
        Route::prefix('course-groups/{courseGroup}/versiones')->name('course-groups.versions.')->scopeBindings()->group(function () {
            Route::post('/', [CourseGroupVersionController::class, 'store'])->name('store');
            Route::get('{version}', [CourseGroupVersionController::class, 'show'])->name('show');
            Route::get('{version}/editar', [CourseGroupVersionController::class, 'edit'])->name('edit');
            Route::put('{version}', [CourseGroupVersionController::class, 'update'])->name('update');
            Route::delete('{version}', [CourseGroupVersionController::class, 'destroy'])->name('destroy');
            Route::patch('{version}/publicar', [CourseGroupVersionController::class, 'publish'])->name('publish');
            Route::patch('{version}/orden', [CourseGroupVersionController::class, 'reorder'])->name('reorder');
        });

        // Reportes de curso por carpeta (docs/reportes/INSTRUCTIVO_REPORTES.md): super admin y admin.
        Route::prefix('course-groups/{courseGroup}/reportes')->name('course-groups.reports.')->scopeBindings()->group(function () {
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
        Route::post('courses/bulk-move', [CourseGroupController::class, 'bulkMove'])->name('courses.bulk-move');
        Route::patch('courses/{course}/move', [CourseGroupController::class, 'moveModule'])->name('courses.move');
        Route::delete('courses/{course}/group', [CourseGroupController::class, 'removeModule'])->name('courses.ungroup');

        // Solo fuera de producción (cintillo "Entorno de pruebas"): en producción responde 404.
        Route::post('entorno-pruebas/apagar-compartido', [TestEnvironmentController::class, 'unshare'])->name('test-environment.unshare');

        Route::middleware('role:'.User::ROLE_SUPER_ADMIN)->group(function () {
            Route::resource('roles', RoleController::class)->except('show');
            Route::resource('permissions', PermissionController::class)->except('show');
            Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity.logs.index');
            // Versiones por país (México, Latam, EUA…): catálogo editable (INSTRUCTIVO_VERSIONES.md §4).
            Route::resource('versiones', MarketController::class)->except('show')->parameters(['versiones' => 'market'])->names('markets');
            Route::patch('versiones/{market}/move', [MarketController::class, 'move'])->name('markets.move');
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

require __DIR__.'/auth.php';

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
