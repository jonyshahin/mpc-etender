<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $projectId = $request->route('project')
            ?? $request->route('project_id')
            ?? $request->input('project_id');

        if (! $projectId) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->isAssignedToProject($projectId)) {
            abort(403, 'You are not assigned to this project.');
        }

        return $next($request);
    }
}
