<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::where('slug', 'super_admin')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@mpc-group.com'],
            [
                'name' => 'MPC Admin',
                'password' => Hash::make('password'),
                'phone' => '+964-770-000-0001',
                'role_id' => $superAdminRole->id,
                'language_pref' => 'en',
                'is_2fa_enabled' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
