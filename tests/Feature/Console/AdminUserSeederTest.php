<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('it creates the super admin when none exists', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', AdminUserSeeder::EMAIL)->sole();

    expect($admin->role->slug)->toBe('super_admin')
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $admin->password))->toBeTrue();
});

/**
 * The regression this seeder was rewritten for: `db:seed` is re-run on live
 * environments to backfill reference tables, and the previous updateOrCreate
 * reset the production admin's password to the literal string 'password' and
 * cleared is_2fa_enabled with it.
 */
test('re-running it leaves an existing admin untouched', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', AdminUserSeeder::EMAIL)->sole();
    $admin->update([
        'password' => Hash::make('a-real-operator-password'),
        'is_2fa_enabled' => true,
        'name' => 'Renamed By Operator',
        'language_pref' => 'ar',
    ]);
    $hashBefore = $admin->fresh()->password;

    $this->seed(AdminUserSeeder::class);

    $after = $admin->fresh();
    expect($after->password)->toBe($hashBefore)
        ->and($after->is_2fa_enabled)->toBeTrue()
        ->and($after->name)->toBe('Renamed By Operator')
        ->and($after->language_pref)->toBe('ar')
        ->and(User::where('email', AdminUserSeeder::EMAIL)->count())->toBe(1);
});

test('it uses the configured bootstrap password when one is set', function () {
    config()->set('mpc.admin.bootstrap_password', 'set-from-the-environment');

    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', AdminUserSeeder::EMAIL)->sole();

    expect(Hash::check('set-from-the-environment', $admin->password))->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeFalse();
});

test('outside local it generates a password rather than defaulting to a known one', function () {
    config()->set('mpc.admin.bootstrap_password', null);
    app()->detectEnvironment(fn () => 'production');

    // Not $this->seed(): db:seed has its own production confirmation.
    Artisan::call('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true]);

    $admin = User::where('email', AdminUserSeeder::EMAIL)->sole();

    expect(Hash::check('password', $admin->password))->toBeFalse();
});
