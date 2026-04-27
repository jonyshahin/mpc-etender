<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;

uses(RefreshDatabase::class);

function userWithRoleSlug(string $slug, string $name): User
{
    $role = Role::firstOrCreate(
        ['slug' => $slug],
        ['name' => $name, 'description' => $name, 'is_system' => true]
    );

    return User::factory()->create(['role_id' => $role->id, 'language_pref' => 'en']);
}

test('procurement_officer cannot access /admin/dashboard', function () {
    $user = userWithRoleSlug('procurement_officer', 'Procurement Officer');

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertForbidden();
});

test('project_manager cannot access /admin/users', function () {
    $user = userWithRoleSlug('project_manager', 'Project Manager');

    $this->actingAs($user)
        ->get('/admin/users')
        ->assertForbidden();
});

test('evaluator cannot access /admin/audit-logs', function () {
    $user = userWithRoleSlug('evaluator', 'Evaluator');

    $this->actingAs($user)
        ->get('/admin/audit-logs')
        ->assertForbidden();
});

test('admin can access /admin/dashboard', function () {
    $user = userWithRoleSlug('admin', 'Admin');

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertOk();
});

test('super_admin passes Horizon::auth and viewPulse gates', function () {
    // The /horizon HTTP route depends on Redis and Horizon's storage tables,
    // which aren't part of the test database. Instead, exercise the two gate
    // definitions directly: that's what BUG-32 actually changed in
    // AppServiceProvider, and it's what protects /horizon and /pulse.
    $superAdmin = userWithRoleSlug('super_admin', 'Super Admin');
    $procurement = userWithRoleSlug('procurement_officer_2', 'Procurement Officer');

    expect(Gate::forUser($superAdmin)->allows('viewPulse'))->toBeTrue();
    expect(Gate::forUser($procurement)->allows('viewPulse'))->toBeFalse();

    // Horizon::check invokes the closure registered via Horizon::auth(...)
    // with a Request whose ->user() resolves the actor. actingAs() doesn't
    // patch the resolver on a non-routed request(), so set it explicitly.
    $request = request();

    $request->setUserResolver(fn () => $superAdmin);
    expect(Horizon::check($request))->toBeTrue();

    $request->setUserResolver(fn () => $procurement);
    expect(Horizon::check($request))->toBeFalse();
});
