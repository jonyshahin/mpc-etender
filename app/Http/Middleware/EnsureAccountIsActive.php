<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Vendor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turn a deactivated account out of a session it already holds.
 *
 * Refusing the sign-in is only half the sanction: sessions outlive the request
 * that created them, so an account deactivated at 10am kept working until its
 * holder chose to log out — which is exactly what someone being removed will
 * not do. The check has to run per request, not once at the door.
 *
 * Both guards are checked because both can be authenticated at the same time:
 * an MPC user browsing the vendor portal holds a `web` session and a `vendor`
 * one independently, and either may have been withdrawn.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user instanceof User && ! $user->is_active) {
            return $this->evict($request, 'web', 'login');
        }

        $vendor = Auth::guard('vendor')->user();

        // Suspension and blacklisting are withdrawals of access too, not just
        // of the right to bid — same predicate the vendor login uses.
        if ($vendor instanceof Vendor && ! $vendor->mayAccessPortal()) {
            return $this->evict($request, 'vendor', 'vendor.login');
        }

        return $next($request);
    }

    /**
     * End the session and send them to the relevant sign-in screen.
     *
     * The session is invalidated rather than merely logged out so nothing
     * flashed or cached under it survives, and the next request arrives as a
     * guest — which is what stops this from redirecting in a loop.
     */
    private function evict(Request $request, string $guard, string $route): Response
    {
        Auth::guard($guard)->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route($route)->with('status', __('auth.account_inactive'));
    }
}
