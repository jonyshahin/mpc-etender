<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Bootstraps the first super-admin account.
 *
 * Safe to re-run: when the account already exists this seeder touches nothing
 * — not the password, not the 2FA flag, not the role. That matters because
 * `db:seed` is routinely re-run on a live environment to backfill reference
 * tables (notification_templates above all), and the updateOrCreate this
 * once used would silently reset a production admin's password to a known
 * string and clear is_2fa_enabled along with it.
 *
 * On first creation the password comes from MPC_ADMIN_PASSWORD when set.
 * Otherwise local and testing get the conventional 'password' and every other
 * environment gets a generated one, printed once.
 */
class AdminUserSeeder extends Seeder
{
    /** Also looked up by DevDataSeeder, which needs an author for its tenders. */
    public const EMAIL = 'admin@mpc-group.com';

    public function run(): void
    {
        if (User::where('email', self::EMAIL)->exists()) {
            $this->command?->info('Admin '.self::EMAIL.' already exists — left untouched (password, 2FA and role preserved).');

            return;
        }

        [$password, $generated] = $this->bootstrapPassword();

        User::create([
            'email' => self::EMAIL,
            'name' => 'MPC Admin',
            'password' => Hash::make($password),
            'phone' => '+964-770-000-0001',
            'role_id' => Role::where('slug', 'super_admin')->firstOrFail()->id,
            'language_pref' => 'en',
            'is_2fa_enabled' => false,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->command?->info('Admin '.self::EMAIL.' created.');

        if ($generated) {
            // The only time this value is ever readable — it is bcrypt-hashed
            // above and cannot be recovered from the row afterwards.
            $this->command?->warn('Generated password: '.$password.'  — record it now, then change it at /settings/security.');
        }
    }

    /**
     * The password to create the account with.
     *
     * @return array{0: string, 1: bool} the password, and whether it was generated
     */
    private function bootstrapPassword(): array
    {
        $configured = config('mpc.admin.bootstrap_password');

        if (is_string($configured) && $configured !== '') {
            return [$configured, false];
        }

        if (app()->environment('local', 'testing')) {
            return ['password', false];
        }

        return [Str::password(20), true];
    }
}
