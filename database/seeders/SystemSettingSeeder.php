<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Approvals
            ['key' => 'approval.level1_threshold', 'value' => '50000', 'group' => 'approvals', 'type' => 'integer', 'description' => 'Award value up to this amount requires Level 1 approval (USD)'],
            ['key' => 'approval.level2_threshold', 'value' => '500000', 'group' => 'approvals', 'type' => 'integer', 'description' => 'Award value up to this amount requires Level 2 approval (USD)'],
            ['key' => 'approval.expiry_days', 'value' => '7', 'group' => 'approvals', 'type' => 'integer', 'description' => 'Days before a pending approval expires'],

            // Notifications
            ['key' => 'notifications.whatsapp_enabled', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean', 'description' => 'Enable WhatsApp notifications via BSP'],
            ['key' => 'notifications.sms_fallback', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean', 'description' => 'Fall back to SMS when WhatsApp delivery fails'],
            ['key' => 'notifications.email_enabled', 'value' => 'true', 'group' => 'notifications', 'type' => 'boolean', 'description' => 'Enable email notifications'],
            ['key' => 'notifications.deadline_reminder_days', 'value' => '3', 'group' => 'notifications', 'type' => 'integer', 'description' => 'Days before submission deadline to send reminder'],

            // Security
            ['key' => 'security.bid_opening_dual_auth', 'value' => 'true', 'group' => 'security', 'type' => 'boolean', 'description' => 'Require two authorized users to open sealed bids'],
            ['key' => 'security.2fa_mandatory_internal', 'value' => 'true', 'group' => 'security', 'type' => 'boolean', 'description' => '2FA is mandatory for MPC internal users'],
            ['key' => 'security.session_timeout_minutes', 'value' => '120', 'group' => 'security', 'type' => 'integer', 'description' => 'Idle session timeout in minutes'],

            // General
            ['key' => 'general.default_currency', 'value' => 'USD', 'group' => 'general', 'type' => 'string', 'description' => 'Default currency for new tenders'],
            ['key' => 'general.default_language', 'value' => 'en', 'group' => 'general', 'type' => 'string', 'description' => 'Default application language'],
            ['key' => 'general.company_name', 'value' => 'MPC Group', 'group' => 'general', 'type' => 'string', 'description' => 'Company name displayed in documents and notifications'],

            // Display
            ['key' => 'display.items_per_page', 'value' => '25', 'group' => 'display', 'type' => 'integer', 'description' => 'Default pagination size'],
            ['key' => 'display.date_format', 'value' => 'Y-m-d', 'group' => 'display', 'type' => 'string', 'description' => 'Date display format'],

            // Tender (BUG-26: enforce buffer between submission_deadline and opening_date)
            ['key' => 'tender.min_hours_between_deadline_and_opening', 'value' => '24', 'group' => 'tender', 'type' => 'integer', 'description' => 'Minimum hours between submission deadline and opening date; enforced when creating tenders or extending deadlines via addenda'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                array_merge($setting, ['updated_at' => now()])
            );
        }
    }
}
