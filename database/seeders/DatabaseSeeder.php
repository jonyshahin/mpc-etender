<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            CategorySeeder::class,
            SystemSettingSeeder::class,
            // Without these, NotificationService resolves an empty channel list
            // and silently sends no email/WhatsApp/SMS at all — see the
            // `$sendChannels` fallback in NotificationService::dispatch().
            NotificationTemplateSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
