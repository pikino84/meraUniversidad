<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseGroupController;
use App\Http\Controllers\CoursePageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\RoleController;
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

        // Carpetas de curso (CLAUDE.md §10): Curso → Idioma → Módulos en orden
        Route::resource('course-groups', CourseGroupController::class);
        Route::patch('course-groups/{courseGroup}/move', [CourseGroupController::class, 'move'])->name('course-groups.move');
        Route::patch('course-groups/{courseGroup}/reorder', [CourseGroupController::class, 'reorder'])->name('course-groups.reorder');
        Route::patch('course-groups/{courseGroup}/publish', [CourseGroupController::class, 'publish'])->name('course-groups.publish');
        Route::post('courses/bulk-move', [CourseGroupController::class, 'bulkMove'])->name('courses.bulk-move');
        Route::patch('courses/{course}/move', [CourseGroupController::class, 'moveModule'])->name('courses.move');
        Route::delete('courses/{course}/group', [CourseGroupController::class, 'removeModule'])->name('courses.ungroup');

        Route::middleware('role:'.User::ROLE_SUPER_ADMIN)->group(function () {
            Route::resource('roles', RoleController::class)->except('show');
            Route::resource('permissions', PermissionController::class)->except('show');
            Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity.logs.index');
            // Papelera de módulos (F2): restaurar lo borrado mientras no se purgue.
            Route::get('papelera', [TrashController::class, 'index'])->name('trash.index');
            Route::post('papelera/{course}/restaurar', [TrashController::class, 'restore'])->withTrashed()->name('trash.restore');
        });
    });
});

require __DIR__.'/auth.php';

// Página pública de cada carpeta de curso (CLAUDE.md §10): /<slug> (español) y /<slug>/en (inglés).
// SIEMPRE al final: cualquier ruta existente (login, cursos, dashboard…) se resuelve antes. Los
// slugs reservados no se pueden asignar a una carpeta (CourseGroup::reservedSlugs()).
Route::get('/{courseGroup}', [CoursePageController::class, 'show'])
    ->where('courseGroup', CourseGroup::SLUG_PATTERN)
    ->name('course-pages.show');
Route::get('/{courseGroup}/{language}', [CoursePageController::class, 'show'])
    ->where(['courseGroup' => CourseGroup::SLUG_PATTERN, 'language' => 'en'])
    ->name('course-pages.show.lang');
