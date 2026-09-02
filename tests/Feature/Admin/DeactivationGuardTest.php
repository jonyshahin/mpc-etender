<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Deactivation has two doors and only one of them was guarded.
 *
 * destroy() checks the deactivate policy and refuses to remove the last super
 * admin. update() reaches the same column — `is_active` is a required field on
 * UpdateUserRequest — and ran neither check: its guards were all inside an
 * `if ($data['role_id'] !== $user->role_id)` branch, so a request that changed
 * only the checkbox skipped every one of them.
 *
 * Harmless while nothing read `is_active`; a lockout the moment sign-in does.
 * Helper names in Pest files are global — hence the suffixes.
 */
function deactivationRole(string $slug, string ...$permissionSlugs): Role
{
    $role = Role::firstOrCreate(
        ['slug' => $slug],
        ['name' => ucfirst($slug), 'is_system' => true],
    );

    foreach ($permissionSlugs as $permissionSlug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $permissionSlug],
            ['name' => $permissionSlug, 'module' => explode('.', $permissionSlug)[0]],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    return $role;
}

function deactivationUser(string $slug, string ...$permissionSlugs): User
{
    return User::factory()->create([
        'role_id' => deactivationRole($slug, ...$permissionSlugs)->id,
        'is_active' => true,
    ]);
}

/** Everything UpdateUserRequest requires, so a failure is about authorization. */
function deactivationPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'name' => $user->name,
        'email' => $user->email,
        'role_id' => $user->role_id,
        'is_active' => true,
    ], $overrides);
}

// ── The bypass ─────────────────────────────────────────────────────────────

test('the last super admin cannot deactivate themselves through the edit form', function () {
    $superAdmin = deactivationUser(Role::SUPER_ADMIN, 'admin.users');

    $this->actingAs($superAdmin)->put(
        route('admin.users.update', $superAdmin),
        deactivationPayload($superAdmin, ['is_active' => false]),
    );

    expect($superAdmin->fresh()->is_active)->toBeTrue();
});

test('nobody can deactivate their own account through the edit form', function () {
    // A second super admin exists, so this is refused for being self-inflicted
    // rather than for being the last one.
    deactivationUser(Role::SUPER_ADMIN, 'admin.users');
    $admin = deactivationUser('admin', 'admin.users');

    $this->actingAs($admin)->put(
        route('admin.users.update', $admin),
        deactivationPayload($admin, ['is_active' => false]),
    );

    expect($admin->fresh()->is_active)->toBeTrue();
});

/**
 * The invariant the whole guard exists for, checked across both doors: there is
 * no sequence of allowed requests that leaves nobody able to grant the role
 * back. Concurrently this holds because the count and the write share one
 * transaction with the super-admin rows locked; sequentially, like this.
 */
test('no route through either endpoint empties the super admins', function () {
    $first = deactivationUser(Role::SUPER_ADMIN, 'admin.users');
    $second = deactivationUser(Role::SUPER_ADMIN, 'admin.users');

    // One may go — the other is still there to administer the system.
    $this->actingAs($first)->delete(route('admin.users.destroy', $second));
    expect($second->fresh()->is_active)->toBeFalse();

    // The survivor may not, by either door.
    $this->actingAs($first)->delete(route('admin.users.destroy', $first));
    $this->actingAs($first)->put(
        route('admin.users.update', $first),
        deactivationPayload($first, ['is_active' => false]),
    );

    expect($first->fresh()->is_active)->toBeTrue()
        ->and(User::where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('slug', Role::SUPER_ADMIN))
            ->count())->toBe(1);
});

// ── Still works ────────────────────────────────────────────────────────────

test('an admin can still deactivate somebody else through the edit form', function () {
    $admin = deactivationUser('admin', 'admin.users');
    $colleague = deactivationUser('viewer');

    $this->actingAs($admin)->put(
        route('admin.users.update', $colleague),
        deactivationPayload($colleague, ['is_active' => false]),
    );

    expect($colleague->fresh()->is_active)->toBeFalse();
});

test('reactivating an account is not blocked by the deactivation guard', function () {
    $admin = deactivationUser('admin', 'admin.users');
    $dormant = deactivationUser('viewer');
    $dormant->update(['is_active' => false]);

    $this->actingAs($admin)->put(
        route('admin.users.update', $dormant),
        deactivationPayload($dormant, ['is_active' => true]),
    );

    expect($dormant->fresh()->is_active)->toBeTrue();
});

// ── Controls that lead somewhere ───────────────────────────────────────────

/**
 * The create dialog was handed the full role list while store() authorizes
 * assignRole — so a plain admin was offered Super Admin and got a 403 on
 * submit, after filling the form in.
 */
test('the create dialog is not offered a role the server would refuse', function () {
    deactivationRole(Role::SUPER_ADMIN);
    $admin = deactivationUser('admin', 'admin.users');

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignableRoles', fn ($roles) => collect($roles)
                ->doesntContain('slug', Role::SUPER_ADMIN))
            // The filter dropdown still lists every role: you may search for
            // super admins without being able to create one.
            ->where('roles', fn ($roles) => collect($roles)
                ->contains('slug', Role::SUPER_ADMIN)));
});

test('a super admin is still offered every role', function () {
    $superAdmin = deactivationUser(Role::SUPER_ADMIN, 'admin.users');

    $this->actingAs($superAdmin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignableRoles', fn ($roles) => collect($roles)
                ->contains('slug', Role::SUPER_ADMIN)));
});

/**
 * The route group gates on the admin *role*; StoreUserRequest gates on the
 * `admin.users` *permission*. A role holding one without the other reached the
 * page and saw an Add User button that 403s.
 */
test('the add user control is hidden without the permission that submitting needs', function () {
    $withoutPermission = deactivationUser('admin');

    $this->actingAs($withoutPermission)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', false));
});

test('the add user control is shown to someone who may use it', function () {
    $admin = deactivationUser('admin', 'admin.users');

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', true));
});
