<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissions = Permission::pluck('id');

        // Super Admin gets everything
        $superAdmin = Role::where('slug', 'super_admin')->first();
        $superAdmin->permissions()->sync($allPermissions);

        // Admin gets everything except level 2/3 approvals
        $admin = Role::where('slug', 'admin')->first();
        $adminPerms = Permission::whereNotIn('slug', ['approvals.level2', 'approvals.level3'])->pluck('id');
        $admin->permissions()->sync($adminPerms);

        // Procurement Officer
        $procurement = Role::where('slug', 'procurement_officer')->first();
        $procurementSlugs = [
            'vendors.view', 'vendors.create', 'vendors.update', 'vendors.qualify', 'vendors.review_docs',
            'tenders.view', 'tenders.create', 'tenders.update', 'tenders.publish', 'tenders.manage_boq',
            'tenders.issue_addenda', 'tenders.answer_clarifications',
            'bids.view', 'bids.open',
            'evaluations.view', 'evaluations.manage_committees', 'evaluations.generate_reports',
            'reports.view', 'reports.export', 'reports.generate',
        ];
        $procurement->permissions()->sync(Permission::whereIn('slug', $procurementSlugs)->pluck('id'));

        // Project Manager
        $pm = Role::where('slug', 'project_manager')->first();
        $pmSlugs = [
            'vendors.view',
            'tenders.view', 'tenders.create', 'tenders.update', 'tenders.publish', 'tenders.cancel',
            'bids.view', 'bids.open',
            'evaluations.view', 'evaluations.manage_committees', 'evaluations.finalize_reports',
            'reports.view', 'reports.export', 'reports.generate',
            'approvals.level1',
        ];
        $pm->permissions()->sync(Permission::whereIn('slug', $pmSlugs)->pluck('id'));

        // Evaluator
        $evaluator = Role::where('slug', 'evaluator')->first();
        $evaluatorSlugs = [
            'tenders.view', 'bids.view', 'evaluations.view', 'evaluations.score',
        ];
        $evaluator->permissions()->sync(Permission::whereIn('slug', $evaluatorSlugs)->pluck('id'));
    }
}
