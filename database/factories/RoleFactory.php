<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'name' => $name,
            // Suffixed so the slug is unique against *seeded* roles too, not just
            // within this Faker instance. jobTitle() can return "Admin", whose
            // slug collides with RoleSeeder's `admin` — which either violated the
            // unique index or, worse, silently handed admin rights to a role a
            // test expected to be unprivileged. Tests that need a real role slug
            // pass it explicitly.
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }
}
