<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
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

        $user = $request->user();
        $vendor = $request->user('vendor');

        AuditLog::forceCreate([
            'user_id' => $user?->id,
            'vendor_id' => $vendor?->id,
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
