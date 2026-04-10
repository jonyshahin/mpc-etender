<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// ── Admin routes ──
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Users
    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

    // Projects
    Route::get('projects', [Admin\ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [Admin\ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}/edit', [Admin\ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('projects/{project}', [Admin\ProjectController::class, 'update'])->name('projects.update');
    Route::post('projects/{project}/assign-users', [Admin\ProjectController::class, 'assignUsers'])->name('projects.assign-users');

    // Roles & Permissions
    Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
    Route::post('roles', [Admin\RoleController::class, 'store'])->name('roles.store');
    Route::put('roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');
    Route::get('roles/{role}/permissions', [Admin\RoleController::class, 'permissions'])->name('roles.permissions');
    Route::put('roles/{role}/permissions', [Admin\RoleController::class, 'updatePermissions'])->name('roles.permissions.update');

    // Categories
    Route::get('categories', [Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    // Settings
    Route::get('settings', [Admin\SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');

    // Audit Logs
    Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
});

require __DIR__.'/settings.php';
