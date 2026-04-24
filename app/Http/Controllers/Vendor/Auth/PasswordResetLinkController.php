<?php

namespace App\Http\Controllers\Vendor\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('vendor/ForgotPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

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
