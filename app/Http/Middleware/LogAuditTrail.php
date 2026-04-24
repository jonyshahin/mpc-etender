<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAuditTrail
{
    /**
     * Uses the terminate pattern for non-blocking logging after the response is sent.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('GET') || $request->is('_debugbar/*', 'horizon/*', 'pulse/*', 'telescope/*')) {
            return;
        }

        // Gate on model instance, not guard name. $request->user() without a
        // guard argument can return whichever guard is currently auth'd in the
        // session — for Vendor-guard requests that would be the Vendor model,
        // whose UUID doesn't exist in `users` and fails the FK constraint.
        $authenticated = $request->user('web') ?? $request->user('vendor');
        $userId = $authenticated instanceof User ? $authenticated->id : null;
        $vendorId = $authenticated instanceof Vendor ? $authenticated->id : null;

        AuditLog::forceCreate([
            'user_id' => $userId,
            'vendor_id' => $vendorId,
            'auditable_type' => 'http_request',
            'auditable_id' => $request->route()?->getName() ?? $request->path(),
            'action' => strtolower($request->method()),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
