<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Vendors
            ['name' => 'View Vendors', 'slug' => 'vendors.view', 'module' => 'vendors'],
            ['name' => 'Create Vendors', 'slug' => 'vendors.create', 'module' => 'vendors'],
            ['name' => 'Update Vendors', 'slug' => 'vendors.update', 'module' => 'vendors'],
            ['name' => 'Delete Vendors', 'slug' => 'vendors.delete', 'module' => 'vendors'],
            ['name' => 'Qualify Vendors', 'slug' => 'vendors.qualify', 'module' => 'vendors'],
            ['name' => 'Review Vendor Documents', 'slug' => 'vendors.review_docs', 'module' => 'vendors'],

            // Tenders
            ['name' => 'View Tenders', 'slug' => 'tenders.view', 'module' => 'tenders'],
            ['name' => 'Create Tenders', 'slug' => 'tenders.create', 'module' => 'tenders'],
            ['name' => 'Update Tenders', 'slug' => 'tenders.update', 'module' => 'tenders'],
            ['name' => 'Delete Tenders', 'slug' => 'tenders.delete', 'module' => 'tenders'],
            ['name' => 'Publish Tenders', 'slug' => 'tenders.publish', 'module' => 'tenders'],
            ['name' => 'Cancel Tenders', 'slug' => 'tenders.cancel', 'module' => 'tenders'],
            ['name' => 'Manage BOQ', 'slug' => 'tenders.manage_boq', 'module' => 'tenders'],
            ['name' => 'Issue Addenda', 'slug' => 'tenders.issue_addenda', 'module' => 'tenders'],
            ['name' => 'Answer Clarifications', 'slug' => 'tenders.answer_clarifications', 'module' => 'tenders'],

            // Bids
            ['name' => 'View Bids', 'slug' => 'bids.view', 'module' => 'bids'],
            ['name' => 'Open Bids', 'slug' => 'bids.open', 'module' => 'bids'],
            ['name' => 'Disqualify Bids', 'slug' => 'bids.disqualify', 'module' => 'bids'],

            // Evaluations
            ['name' => 'View Evaluations', 'slug' => 'evaluations.view', 'module' => 'evaluations'],
            ['name' => 'Score Bids', 'slug' => 'evaluations.score', 'module' => 'evaluations'],
            ['name' => 'Manage Committees', 'slug' => 'evaluations.manage_committees', 'module' => 'evaluations'],
            ['name' => 'Generate Reports', 'slug' => 'evaluations.generate_reports', 'module' => 'evaluations'],
            ['name' => 'Finalize Reports', 'slug' => 'evaluations.finalize_reports', 'module' => 'evaluations'],

            // Reports
            ['name' => 'View Reports', 'slug' => 'reports.view', 'module' => 'reports'],
            ['name' => 'Export Reports', 'slug' => 'reports.export', 'module' => 'reports'],
            ['name' => 'Generate Reports', 'slug' => 'reports.generate', 'module' => 'reports'],

            // Admin
            ['name' => 'Manage Users', 'slug' => 'admin.users', 'module' => 'admin'],
            ['name' => 'Manage Roles', 'slug' => 'admin.roles', 'module' => 'admin'],
            ['name' => 'Manage Settings', 'slug' => 'admin.settings', 'module' => 'admin'],
            ['name' => 'View Audit Logs', 'slug' => 'admin.audit_logs', 'module' => 'admin'],
            ['name' => 'Manage Categories', 'slug' => 'admin.categories', 'module' => 'admin'],
            ['name' => 'Manage Projects', 'slug' => 'admin.projects', 'module' => 'admin'],

            // Approvals
            ['name' => 'Approve Level 1', 'slug' => 'approvals.level1', 'module' => 'approvals'],
            ['name' => 'Approve Level 2', 'slug' => 'approvals.level2', 'module' => 'approvals'],
            ['name' => 'Approve Level 3', 'slug' => 'approvals.level3', 'module' => 'approvals'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(['slug' => $perm['slug']], $perm);
        }
    }
}
