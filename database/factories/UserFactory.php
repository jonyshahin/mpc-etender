<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->phoneNumber(),
            'role_id' => Role::factory(),
            'language_pref' => fake()->randomElement(['en', 'ar']),
            'is_2fa_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'avatar_path' => null,
            'is_active' => true,
            'last_login_at' => fake()->optional()->dateTimeBetween('-30 days'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'is_2fa_enabled' => true,
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Build a fresh role and seed it with the requested permission slugs,
     * then assign it to the user. Each call mints a new role so tests
     * never share a mutable role across cases.
     */
    private function withRoleHavingPermissions(array $slugs): static
    {
        return $this->afterCreating(function (User $user) use ($slugs) {
            $role = Role::factory()->create();
            foreach ($slugs as $slug) {
                $perm = Permission::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace('.', ' ', $slug)), 'module' => explode('.', $slug)[0]]
                );
                $role->permissions()->attach($perm->id);
            }
            $user->update(['role_id' => $role->id]);
        });
    }

    public function admin(): static
    {
        return $this->withRoleHavingPermissions([
            'tenders.view', 'tenders.create', 'tenders.update', 'tenders.publish', 'tenders.cancel',
            'tenders.delete', 'tenders.manage_boq', 'tenders.issue_addenda', 'tenders.answer_clarifications',
            'vendors.view', 'vendors.create', 'vendors.update', 'vendors.qualify',
            'bids.view', 'bids.open', 'evaluations.view', 'evaluations.manage_committees',
        ]);
    }

    public function procurementOfficerWithoutPublish(): static
    {
        return $this->withRoleHavingPermissions([
            'tenders.view', 'tenders.create', 'tenders.update', 'tenders.manage_boq',
            // tenders.publish intentionally omitted
        ]);
    }

    public function projectManager(): static
    {
        return $this->withRoleHavingPermissions([
            'tenders.view', 'tenders.create', 'tenders.update', 'tenders.publish', 'tenders.cancel',
        ]);
    }

    public function evaluator(): static
    {
        return $this->withRoleHavingPermissions([
            'tenders.view', 'evaluations.view', 'evaluations.score',
        ]);
    }
}
