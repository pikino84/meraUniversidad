<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\LoungeController;
use App\Http\Controllers\CourseController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('auth.login');
})->middleware('guest')->name('home');
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');
//Rutas protegidas por autenticación
Route::middleware('auth')->group(function () {
    // SUPER ADMIN + ADMIN -> Admin Panel
    Route::middleware(['role:super admin|admin'])->group(function () {
        Route::resource('users', UserController::class);
        Route::resource('roles', RoleController::class);
        Route::resource('permissions', PermissionController::class);
        
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity.logs.index');
        Route::resource('lounges', LoungeController::class);
        // Perfil
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::resource('courses', CourseController::class);
    });
});

Route::get('/cursos', [\App\Http\Controllers\CourseController::class, 'publicIndex']);
require __DIR__.'/auth.php';