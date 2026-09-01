<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The admin role must not be a route to super admin.
 *
 * There was no UserPolicy or RolePolicy. `admin.users` was a single flat
 * permission and nothing looked at which role was being assigned or whose
 * account was being edited, so anyone holding the admin role could hand
 * themselves or anyone else super admin in one request, demote every super
 * admin, or deactivate all of them. Helper names in Pest files are global.
 */
function roleNamed(string $slug, bool $system = true): Role
{
    return Role::firstOrCreate(
        ['slug' => $slug],
        ['name' => ucfirst($slug), 'is_system' => $system],
    );
}

function userInRole(string $slug, string ...$permissionSlugs): User
{
    $role = roleNamed($slug);

    foreach ($permissionSlugs as $permissionSlug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $permissionSlug],
            ['name' => $permissionSlug, 'module' => explode('.', $permissionSlug)[0]],
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

/** The full payload UpdateUserRequest expects, so failures are about authorization. */
function userPayload(User $user, string $roleId): array
{
    return [
        'name' => $user->name,
        'email' => $user->email,
        'role_id' => $roleId,
        'is_active' => true,
    ];
}

// ── The escalation paths, all previously open ──────────────────────────────

test('an admin cannot promote another user to super admin', function () {
    $superAdmin = roleNamed('super_admin');
    $admin = userInRole('admin', 'admin.users');
    $victim = User::factory()->create(['role_id' => roleNamed('viewer', false)->id]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $victim), userPayload($victim, $superAdmin->id));

    expect($victim->fresh()->role->slug)->toBe('viewer');
});

test('an admin cannot demote an existing super admin', function () {
    $admin = userInRole('admin', 'admin.users');
    $target = User::factory()->create(['role_id' => roleNamed('super_admin')->id]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $target), userPayload($target, roleNamed('viewer', false)->id));

    expect($target->fresh()->role->slug)->toBe('super_admin');
});

test('an admin cannot promote themselves to super admin', function () {
    $superAdmin = roleNamed('super_admin');
    $admin = userInRole('admin', 'admin.users');

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), userPayload($admin, $superAdmin->id));

    expect($admin->fresh()->role->slug)->toBe('admin');
});

test('an admin cannot create a new super admin account', function () {
    $superAdmin = roleNamed('super_admin');
    $admin = userInRole('admin', 'admin.users');

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Backdoor',
        'email' => 'backdoor@mpc-group.com',
        'password' => 'Str0ng!Passphrase#2026',
        'password_confirmation' => 'Str0ng!Passphrase#2026',
        'role_id' => $superAdmin->id,
        'is_active' => true,
    ]);

    expect(User::where('email', 'backdoor@mpc-group.com')->exists())->toBeFalse();
});

test('an admin cannot deactivate a super admin', function () {
    $admin = userInRole('admin', 'admin.users');
    $target = User::factory()->create(['role_id' => roleNamed('super_admin')->id]);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $target));

    expect($target->fresh()->is_active)->toBeTrue();
});

test('an admin cannot grant their own role a permission it did not have', function () {
    $admin = userInRole('admin', 'admin.roles');
    $prize = Permission::firstOrCreate(
        ['slug' => 'bids.open'],
        ['name' => 'Open Bids', 'module' => 'bids'],
    );

    $this->actingAs($admin)
        ->put(route('admin.roles.permissions.update', $admin->role), ['permission_ids' => [$prize->id]]);

    expect($admin->fresh()->hasPermission('bids.open'))->toBeFalse();
});

test('an admin cannot rewrite the super admin role permissions', function () {
    $superAdminRole = roleNamed('super_admin');
    $admin = userInRole('admin', 'admin.roles');
    $harmless = Permission::firstOrCreate(
        ['slug' => 'vendors.view'],
        ['name' => 'View Vendors', 'module' => 'vendors'],
    );

    $this->actingAs($admin)
        ->put(route('admin.roles.permissions.update', $superAdminRole), ['permission_ids' => [$harmless->id]]);

    expect($superAdminRole->fresh()->permissions()->pluck('slug')->all())->not->toBe(['vendors.view']);
});

// ── Rule 5: a super admin must always remain ───────────────────────────────

test('the last super admin cannot be demoted', function () {
    $only = userInRole('super_admin', 'admin.users');

    // By another super admin — there is none — so use themselves, which rule 2
    // blocks anyway; the point is that no path removes the last one.
    $second = User::factory()->create(['role_id' => roleNamed('super_admin')->id]);

    $this->actingAs($second)
        ->put(route('admin.users.update', $only), userPayload($only, roleNamed('viewer', false)->id));

    expect($only->fresh()->role->slug)->toBe('viewer', 'two exist, so demoting one is allowed');

    // Now only $second remains.
    $this->actingAs($only->fresh())->delete(route('admin.users.destroy', $second));

    expect($second->fresh()->is_active)->toBeTrue();
});

test('the last super admin cannot be deactivated', function () {
    $onlySuper = User::factory()->create(['role_id' => roleNamed('super_admin')->id]);
    $admin = userInRole('admin', 'admin.users');

    $this->actingAs($admin)->delete(route('admin.users.destroy', $onlySuper));

    expect($onlySuper->fresh()->is_active)->toBeTrue();
});

// ── The other half: legitimate administration still works ──────────────────

test('a super admin can still promote someone to super admin', function () {
    $superAdminRole = roleNamed('super_admin');
    $actor = User::factory()->create(['role_id' => $superAdminRole->id]);
    $actor->role->permissions()->syncWithoutDetaching([
        Permission::firstOrCreate(['slug' => 'admin.users'], ['name' => 'Users', 'module' => 'admin'])->id,
    ]);
    $colleague = User::factory()->create(['role_id' => roleNamed('viewer', false)->id]);

    $this->actingAs($actor)
        ->put(route('admin.users.update', $colleague), userPayload($colleague, $superAdminRole->id));

    expect($colleague->fresh()->role->slug)->toBe('super_admin');
});

test('an admin can still administer an ordinary user', function () {
    $admin = userInRole('admin', 'admin.users');
    $viewer = roleNamed('viewer', false);
    $officer = roleNamed('procurement_officer');
    $target = User::factory()->create(['role_id' => $viewer->id]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $target), userPayload($target, $officer->id));

    expect($target->fresh()->role->slug)->toBe('procurement_officer');
});

/**
 * Every seeded role is is_system, so refusing those outright would leave the
 * permissions screen unable to configure anything real.
 */
test('an admin can still configure another non-super-admin system role', function () {
    $admin = userInRole('admin', 'admin.roles');
    $evaluator = roleNamed('evaluator');
    $permission = Permission::firstOrCreate(
        ['slug' => 'evaluations.score'],
        ['name' => 'Score', 'module' => 'evaluations'],
    );

    $this->actingAs($admin)
        ->put(route('admin.roles.permissions.update', $evaluator), ['permission_ids' => [$permission->id]]);

    expect($evaluator->fresh()->permissions()->pluck('slug')->all())->toBe(['evaluations.score']);
});
