<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Públicas:   /, /cursos (catálogo)
| auth:       perfil propio (cualquier usuario autenticado)
| panel:      super admin | admin  → dashboard, usuarios, categorías, cursos
| super:      super admin          → roles, permisos, historial
*/

Route::redirect('/', '/login')->name('home');

Route::get('/cursos', [CatalogController::class, 'index'])->name('catalog.index');

$panelRoles = 'role:'.implode('|', User::PANEL_ROLES);

Route::middleware('auth')->group(function () use ($panelRoles) {
    // Perfil propio: cualquier usuario autenticado puede cambiar sus datos y contraseña.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware($panelRoles)->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('users', UserController::class)->except('show');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('courses', CourseController::class)->except('show');

        Route::middleware('role:'.User::ROLE_SUPER_ADMIN)->group(function () {
            Route::resource('roles', RoleController::class)->except('show');
            Route::resource('permissions', PermissionController::class)->except('show');
            Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity.logs.index');
        });
    });
});

require __DIR__.'/auth.php';
