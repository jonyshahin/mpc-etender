<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    public function definition(): array
    {
        $modules = ['vendors', 'tenders', 'bids', 'evaluations', 'admin', 'reports'];
        $actions = ['view', 'create', 'update', 'delete', 'export'];
        $module = fake()->randomElement($modules);
        $action = fake()->randomElement($actions);

        return [
            'name' => ucfirst($action).' '.ucfirst($module),
            'slug' => $module.'.'.$action.'.'.fake()->unique()->randomNumber(4),
            'module' => $module,
            'description' => fake()->sentence(),
        ];
    }
}
