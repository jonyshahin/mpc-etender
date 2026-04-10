<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'total_users' => User::count(),
                'active_projects' => Project::active()->count(),
                'active_tenders' => Tender::where('status', 'published')->count(),
                'pending_vendors' => Vendor::where('prequalification_status', 'pending')->count(),
            ],
            'recentActivity' => ActivityLog::with('user:id,name')
                ->latest('created_at')
                ->take(15)
                ->get(['id', 'user_id', 'description', 'subject_type', 'subject_id', 'created_at']),
        ]);
    }
}
