<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * BUG-06 — the admin user form requests rejected `ku` even though SetLocale
 * accepts en/ar/ku and lang/ku.json ships, so Kurdish could never be saved.
 *
 * Also pins the nullability fix: users.language_pref is NOT NULL DEFAULT 'en',
 * and the rules were `nullable`, so an empty value would reach the insert as
 * NULL and 500 on MySQL while passing on SQLite.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function adminForUserLanguage(): User
{
    $role = Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);
    $permission = Permission::create([
        'name' => 'Admin Users',
        'slug' => 'admin.users',
        'module' => 'admin',
    ]);
    $role->permissions()->attach($permission->id);

    return User::factory()->create(['role_id' => $role->id]);
}

function newUserPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Rebin Karim',
        'email' => 'rebin.karim@mpc-group.com',
        'password' => 'Str0ng-Passw0rd!',
        'phone' => '+9647701112233',
        'role_id' => Role::factory()->create()->id,
        'is_active' => true,
    ], $overrides);
}

test('an admin can create a user with Kurdish as their language', function () {
    $admin = adminForUserLanguage();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), newUserPayload(['language_pref' => 'ku']))
        ->assertSessionHasNoErrors();

    expect(User::firstWhere('email', 'rebin.karim@mpc-group.com')->language_pref)->toBe('ku');
});

test('an admin can switch an existing user to Kurdish', function () {
    $admin = adminForUserLanguage();
    $user = User::factory()->create(['language_pref' => 'en']);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'language_pref' => 'ku',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->language_pref)->toBe('ku');
});

test('English and Arabic are still accepted', function () {
    $admin = adminForUserLanguage();

    foreach (['en', 'ar'] as $index => $locale) {
        $this->actingAs($admin)
            ->post(route('admin.users.store'), newUserPayload([
                'email' => "locale{$index}@mpc-group.com",
                'language_pref' => $locale,
            ]))
            ->assertSessionHasNoErrors();

        expect(User::firstWhere('email', "locale{$index}@mpc-group.com")->language_pref)->toBe($locale);
    }
});

test('an unsupported language is rejected', function () {
    $admin = adminForUserLanguage();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), newUserPayload(['language_pref' => 'fr']))
        ->assertSessionHasErrors('language_pref');

    expect(User::whereEmail('rebin.karim@mpc-group.com')->exists())->toBeFalse();
});

test('an explicitly empty language is rejected rather than written as NULL', function () {
    $admin = adminForUserLanguage();

    // ConvertEmptyStringsToNull turns '' into null; users.language_pref is
    // NOT NULL, so without `required` this would reach the insert and 500 on
    // MySQL (SQLite would silently accept it).
    $this->actingAs($admin)
        ->post(route('admin.users.store'), newUserPayload(['language_pref' => '']))
        ->assertSessionHasErrors('language_pref');

    expect(User::whereEmail('rebin.karim@mpc-group.com')->exists())->toBeFalse();
});

test('omitting the language falls back to the column default', function () {
    $admin = adminForUserLanguage();
    $payload = newUserPayload();
    unset($payload['language_pref']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(User::firstWhere('email', 'rebin.karim@mpc-group.com')->language_pref)->toBe('en');
});
