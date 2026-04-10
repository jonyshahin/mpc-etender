<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Full system access', 'is_system' => true],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrative access excluding system config', 'is_system' => true],
            ['name' => 'Procurement Officer', 'slug' => 'procurement_officer', 'description' => 'Manages tenders, BOQ, and vendor qualification', 'is_system' => true],
            ['name' => 'Project Manager', 'slug' => 'project_manager', 'description' => 'Oversees projects and approves procurement actions', 'is_system' => true],
            ['name' => 'Evaluator', 'slug' => 'evaluator', 'description' => 'Scores bids on assigned evaluation committees', 'is_system' => true],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
