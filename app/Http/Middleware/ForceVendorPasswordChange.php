<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceVendorPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $vendor = $request->user('vendor');

        if ($vendor && $vendor->must_change_password) {
            $allow = [
                'vendor.password.change.show',
                'vendor.password.change',
                'vendor.logout',
            ];

            if (! in_array($request->route()?->getName(), $allow, true)) {
                return redirect()->route('vendor.password.change.show');
            }
        }

        return $next($request);
    }
}
