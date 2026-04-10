<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::with('user:id,name')
            ->select('id', 'user_id', 'auditable_type', 'auditable_id', 'action', 'old_values', 'new_values', 'ip_address', 'created_at');

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($entityType = $request->input('entity_type')) {
            $query->where('auditable_type', $entityType);
        }

        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        $query->latest('created_at');

        return Inertia::render('admin/AuditLogs/Index', [
            'logs' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only('user_id', 'action', 'entity_type', 'from', 'to'),
        ]);
    }
}
