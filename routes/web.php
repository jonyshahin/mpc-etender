<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Approval;
use App\Http\Controllers\Dashboard;
use App\Http\Controllers\Evaluation;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Notification;
use App\Http\Controllers\Tender;
use App\Http\Controllers\Vendor;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

// ── Language switch ──
Route::put('user/language', [LanguageController::class, 'update'])->name('language.update');

// ── Notifications (MPC users) ──
Route::middleware(['auth', 'verified'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [Notification\NotificationController::class, 'index'])->name('index');
    Route::post('{notification}/read', [Notification\NotificationController::class, 'markRead'])->name('read');
    Route::post('mark-all-read', [Notification\NotificationController::class, 'markAllRead'])->name('read-all');
    Route::get('recent', [Notification\NotificationController::class, 'recent'])->name('recent');
});

// ── Dashboards ──
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard/portfolio', [Dashboard\DashboardController::class, 'portfolio'])->name('dashboard.portfolio');
    Route::get('dashboard/project/{project}', [Dashboard\DashboardController::class, 'project'])->name('dashboard.project');
});

// ── Vendor public routes (registration & login & password reset) ──
Route::prefix('vendor')->name('vendor.')->group(function () {
    Route::middleware('guest:vendor')->group(function () {
        Route::get('register', [Vendor\RegisterController::class, 'create'])->name('register');
        Route::post('register', [Vendor\RegisterController::class, 'store'])->name('register.store');
        Route::get('login', [Vendor\LoginController::class, 'create'])->name('login');
        Route::post('login', [Vendor\LoginController::class, 'store'])->name('login.store');

        Route::get('forgot-password', [Vendor\Auth\PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [Vendor\Auth\PasswordResetLinkController::class, 'store'])->name('password.email');
        Route::get('reset-password/{token}', [Vendor\Auth\NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [Vendor\Auth\NewPasswordController::class, 'store'])->name('password.update');
    });

    Route::post('logout', [Vendor\LoginController::class, 'destroy'])->name('logout')
        ->middleware('auth:vendor');
});

// ── Vendor authenticated routes ──
Route::middleware(['auth:vendor', 'vendor.password.required'])->prefix('vendor')->name('vendor.')->group(function () {
    // Self-service password change (always accessible while authenticated,
    // including when must_change_password is true).
    Route::get('password/change', [Vendor\Auth\PasswordController::class, 'edit'])->name('password.change.show');
    Route::put('password/change', [Vendor\Auth\PasswordController::class, 'update'])->name('password.change');

    Route::get('dashboard', [Vendor\DashboardController::class, 'index'])->name('dashboard');

    Route::get('profile', [Vendor\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [Vendor\ProfileController::class, 'update'])->name('profile.update');

    Route::get('documents', [Vendor\DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [Vendor\DocumentController::class, 'store'])->name('documents.store');
    Route::delete('documents/{document}', [Vendor\DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('categories', [Vendor\CategoryController::class, 'index'])->name('categories.index');
    // PUT /vendor/categories removed (C.1) — category changes now go through the
    // request-and-approve workflow below.

    // Category change requests (request-and-approve workflow)
    Route::get('category-requests', [Vendor\CategoryRequestController::class, 'index'])->name('category-requests.index');
    Route::get('category-requests/create', [Vendor\CategoryRequestController::class, 'create'])->name('category-requests.create');
    Route::post('category-requests', [Vendor\CategoryRequestController::class, 'store'])->name('category-requests.store');
    Route::get('category-requests/{categoryRequest}', [Vendor\CategoryRequestController::class, 'show'])->name('category-requests.show');
    Route::get('category-requests/{categoryRequest}/evidence/{evidence}/download', [Vendor\CategoryRequestController::class, 'downloadEvidence'])->name('category-requests.evidence.download');
    Route::delete('category-requests/{categoryRequest}', [Vendor\CategoryRequestController::class, 'destroy'])->name('category-requests.destroy');

    // Notifications
    Route::get('notifications', [Notification\NotificationController::class, 'vendorIndex'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [Notification\NotificationController::class, 'vendorMarkRead'])->name('notifications.read');

    // Tender browsing
    Route::get('tenders', [Vendor\TenderBrowseController::class, 'index'])->name('tenders.index');
    Route::get('tenders/{tender}', [Vendor\TenderBrowseController::class, 'show'])->name('tenders.show');

    // Clarifications (vendor asking)
    Route::post('tenders/{tender}/clarifications', [Tender\ClarificationController::class, 'store'])->name('tenders.clarifications.store');

    // Bids
    Route::get('bids', [Vendor\BidController::class, 'index'])->name('bids.index');
    Route::get('bids/{bid}', [Vendor\BidController::class, 'show'])->name('bids.show');
    Route::get('tenders/{tender}/bid', [Vendor\BidController::class, 'create'])->name('bids.create');
    Route::put('bids/{bid}', [Vendor\BidController::class, 'update'])->name('bids.update');
    Route::post('bids/{bid}/submit', [Vendor\BidController::class, 'submit'])->name('bids.submit');
    Route::post('bids/{bid}/withdraw', [Vendor\BidController::class, 'withdraw'])->name('bids.withdraw');

    // Bid documents (technical / financial / single envelope attachments)
    Route::post('bids/{bid}/documents', [Vendor\BidController::class, 'storeDocument'])->name('bids.documents.store');
    Route::delete('bids/{bid}/documents/{document}', [Vendor\BidController::class, 'destroyDocument'])->name('bids.documents.destroy');
    Route::get('bids/{bid}/documents/{document}/download', [Vendor\BidController::class, 'downloadDocument'])->name('bids.documents.download');
});

// ── Tender management routes (MPC users) ──
Route::middleware(['auth', 'verified'])->prefix('tenders')->name('tenders.')->group(function () {
    Route::get('/', [Tender\TenderController::class, 'index'])->name('index');
    Route::get('create', [Tender\TenderController::class, 'create'])->name('create');
    Route::post('/', [Tender\TenderController::class, 'store'])->name('store');
    Route::get('{tender}', [Tender\TenderController::class, 'show'])->name('show');
    Route::get('{tender}/edit', [Tender\TenderController::class, 'edit'])->name('edit');
    Route::put('{tender}', [Tender\TenderController::class, 'update'])->name('update');
    Route::post('{tender}/publish', [Tender\TenderController::class, 'publish'])->name('publish');
    Route::post('{tender}/cancel', [Tender\TenderController::class, 'cancel'])->name('cancel');

    // BOQ
    Route::post('{tender}/boq-sections', [Tender\BoqController::class, 'storeSection'])->name('boq.sections.store');
    Route::put('{tender}/boq-sections/{section}', [Tender\BoqController::class, 'updateSection'])->name('boq.sections.update');
    Route::delete('{tender}/boq-sections/{section}', [Tender\BoqController::class, 'destroySection'])->name('boq.sections.destroy');
    Route::post('{tender}/boq-sections/{section}/items', [Tender\BoqController::class, 'storeItem'])->name('boq.items.store');
    Route::put('{tender}/boq-items/{item}', [Tender\BoqController::class, 'updateItem'])->name('boq.items.update');
    Route::delete('{tender}/boq-items/{item}', [Tender\BoqController::class, 'destroyItem'])->name('boq.items.destroy');
    Route::post('{tender}/boq-import', [Tender\BoqController::class, 'import'])->name('boq.import');

    // Documents
    Route::post('{tender}/documents', [Tender\TenderDocumentController::class, 'store'])->name('documents.store');
    Route::delete('{tender}/documents/{doc}', [Tender\TenderDocumentController::class, 'destroy'])->name('documents.destroy');

    // Addenda
    Route::post('{tender}/addenda', [Tender\AddendumController::class, 'store'])->name('addenda.store');

    // Clarifications (MPC answering)
    Route::put('{tender}/clarifications/{clarification}/answer', [Tender\ClarificationController::class, 'answer'])->name('clarifications.answer');
    Route::post('{tender}/clarifications/{clarification}/publish', [Tender\ClarificationController::class, 'publish'])->name('clarifications.publish');

    // Evaluation criteria
    Route::post('{tender}/evaluation-criteria', [Tender\EvaluationCriteriaController::class, 'store'])->name('criteria.store');
    Route::put('{tender}/evaluation-criteria/{criterion}', [Tender\EvaluationCriteriaController::class, 'update'])->name('criteria.update');
    Route::delete('{tender}/evaluation-criteria/{criterion}', [Tender\EvaluationCriteriaController::class, 'destroy'])->name('criteria.destroy');

    // Bid opening & evaluation
    Route::post('{tender}/open-bids', [Evaluation\BidOpeningController::class, 'open'])->name('open-bids');
    Route::get('{tender}/bid-summary', [Evaluation\BidOpeningController::class, 'summary'])->name('bid-summary');

    // Committees
    Route::get('{tender}/committees', [Evaluation\CommitteeController::class, 'index'])->name('committees.index');
    Route::post('{tender}/committees', [Evaluation\CommitteeController::class, 'store'])->name('committees.store');
    Route::put('{tender}/committees/{committee}', [Evaluation\CommitteeController::class, 'update'])->name('committees.update');
    Route::post('{tender}/committees/{committee}/members', [Evaluation\CommitteeController::class, 'addMember'])->name('committees.members.store');
    Route::delete('{tender}/committees/{committee}/members/{member}', [Evaluation\CommitteeController::class, 'removeMember'])->name('committees.members.destroy');

    // Two-envelope workflow
    Route::post('{tender}/complete-technical', [Evaluation\EnvelopeController::class, 'completeTechnical'])->name('complete-technical');
    Route::post('{tender}/complete-financial', [Evaluation\EnvelopeController::class, 'completeFinancial'])->name('complete-financial');

    // Evaluation report
    Route::post('{tender}/evaluation-report', [Evaluation\ReportController::class, 'generate'])->name('report.generate');
    Route::get('{tender}/evaluation-report', [Evaluation\ReportController::class, 'show'])->name('report.show');
    Route::get('{tender}/evaluation-report/pdf', [Evaluation\ReportController::class, 'downloadPdf'])->name('report.pdf');
});

// ── Evaluation scoring routes ──
Route::middleware(['auth', 'verified'])->prefix('evaluations')->name('evaluations.')->group(function () {
    Route::get('{tender}/score', [Evaluation\ScoringController::class, 'index'])->name('score.index');
    Route::get('{tender}/score/{bid}', [Evaluation\ScoringController::class, 'scoreBid'])->name('score.bid');
    Route::post('{tender}/score/{bid}', [Evaluation\ScoringController::class, 'storeScores'])->name('score.store');
    Route::get('{tender}/my-progress', [Evaluation\ScoringController::class, 'myProgress'])->name('my-progress');
});

// ── Approval routes ──
Route::middleware(['auth', 'verified'])->prefix('approvals')->name('approvals.')->group(function () {
    Route::get('/', [Approval\ApprovalController::class, 'index'])->name('index');
    Route::get('{approval}', [Approval\ApprovalController::class, 'show'])->name('show');
    Route::post('{approval}/approve', [Approval\ApprovalController::class, 'approve'])->name('approve');
    Route::post('{approval}/reject', [Approval\ApprovalController::class, 'reject'])->name('reject');
    Route::post('{approval}/delegate', [Approval\ApprovalController::class, 'delegate'])->name('delegate');
});

// Request approval route (under tenders)
Route::middleware(['auth', 'verified'])->post('tenders/{tender}/request-approval', [Approval\ApprovalController::class, 'requestApproval'])->name('tenders.request-approval');

// ── Admin routes ──
Route::middleware(['auth', 'verified', 'role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
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
    Route::post('projects/{project}/assign-users', [Admin\ProjectController::class, 'addUser'])->name('projects.assign-users');
    Route::put('projects/{project}/users/{user}', [Admin\ProjectController::class, 'updateUserRole'])->name('projects.users.update');
    Route::delete('projects/{project}/users/{user}', [Admin\ProjectController::class, 'removeUser'])->name('projects.users.destroy');

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
    Route::post('vendors/{vendor}/send-password-reset', [Admin\VendorController::class, 'sendPasswordReset'])->name('vendors.send-password-reset');
    Route::post('vendors/{vendor}/force-temporary-password', [Admin\VendorController::class, 'forceTemporaryPassword'])->name('vendors.force-temporary-password');

    // Vendor category change requests — admin review queue
    Route::get('vendor-category-requests', [Admin\VendorCategoryRequestController::class, 'index'])->name('vendor-category-requests.index');
    Route::get('vendor-category-requests/{categoryRequest}', [Admin\VendorCategoryRequestController::class, 'show'])->name('vendor-category-requests.show');
    Route::post('vendor-category-requests/{categoryRequest}/approve', [Admin\VendorCategoryRequestController::class, 'approve'])->name('vendor-category-requests.approve');
    Route::post('vendor-category-requests/{categoryRequest}/reject', [Admin\VendorCategoryRequestController::class, 'reject'])->name('vendor-category-requests.reject');
    Route::get('vendor-category-requests/evidence/{evidence}/download', [Admin\VendorCategoryRequestController::class, 'downloadEvidence'])->name('vendor-category-requests.evidence.download');

    // Audit Logs
    Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
});

require __DIR__.'/settings.php';
