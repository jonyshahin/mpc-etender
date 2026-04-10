<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Vendor;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// ── Vendor public routes (registration & login) ──
Route::prefix('vendor')->name('vendor.')->group(function () {
    Route::middleware('guest:vendor')->group(function () {
        Route::get('register', [Vendor\RegisterController::class, 'create'])->name('register');
        Route::post('register', [Vendor\RegisterController::class, 'store'])->name('register.store');
        Route::get('login', [Vendor\LoginController::class, 'create'])->name('login');
        Route::post('login', [Vendor\LoginController::class, 'store'])->name('login.store');
    });

    Route::post('logout', [Vendor\LoginController::class, 'destroy'])->name('logout')
        ->middleware('auth:vendor');
});

// ── Vendor authenticated routes ──
Route::middleware('auth:vendor')->prefix('vendor')->name('vendor.')->group(function () {
    Route::get('dashboard', [Vendor\DashboardController::class, 'index'])->name('dashboard');

    Route::get('profile', [Vendor\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [Vendor\ProfileController::class, 'update'])->name('profile.update');

    Route::get('documents', [Vendor\DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [Vendor\DocumentController::class, 'store'])->name('documents.store');
    Route::delete('documents/{document}', [Vendor\DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('categories', [Vendor\CategoryController::class, 'index'])->name('categories.index');
    Route::put('categories', [Vendor\CategoryController::class, 'update'])->name('categories.update');
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

    // Vendors
    Route::get('vendors', [Admin\VendorController::class, 'index'])->name('vendors.index');
    Route::get('vendors/{vendor}', [Admin\VendorController::class, 'show'])->name('vendors.show');
    Route::put('vendors/{vendor}/prequalify', [Admin\VendorController::class, 'prequalify'])->name('vendors.prequalify');
    Route::put('vendors/{vendor}/reject', [Admin\VendorController::class, 'reject'])->name('vendors.reject');
    Route::put('vendors/{vendor}/suspend', [Admin\VendorController::class, 'suspend'])->name('vendors.suspend');

    // Audit Logs
    Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
});

require __DIR__.'/settings.php';
