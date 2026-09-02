<?php

namespace App\Http\Controllers\Vendor\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('vendor/ForgotPassword');
    }

    /**
     * Reset requests allowed from one IP before it locks.
     *
     * The broker already throttles one token per address per minute
     * (config/auth.php), which covers an attacker hammering a single address.
     * Nothing covered one walking a list — every match sends real mail.
     */
    private const MAX_ATTEMPTS = 6;

    private const DECAY_SECONDS = 60;

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $key = 'vendor-password-reset|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            // Thrown before the lookup, so a locked requester learns nothing
            // about the address they just submitted either.
            throw ValidationException::withMessages([
                'email' => __('auth.vendor_reset_throttled', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        // Non-enumerable: always respond with the same success toast even when
        // the email doesn't belong to a vendor, so attackers can't probe for
        // registered accounts. Still write an audit row on match so admins can
        // track reset activity.
        $vendor = Vendor::where('email', $request->input('email'))->first();

        if ($vendor) {
            Password::broker('vendors')->sendResetLink(['email' => $vendor->email]);

            AuditLog::create([
                'user_id' => null,
                'vendor_id' => $vendor->id,
                'auditable_type' => Vendor::class,
                'auditable_id' => $vendor->id,
                'action' => 'password_reset_requested',
                'old_values' => null,
                'new_values' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.vendor_password_reset_link_sent'),
        ]);

        return back();
    }
}
