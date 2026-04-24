<?php

namespace App\Http\Controllers\Vendor\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Vendor;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('vendor/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::broker('vendors')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Vendor $vendor, string $password) {
                $vendor->forceFill([
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                AuditLog::create([
                    'user_id' => null,
                    'vendor_id' => $vendor->id,
                    'auditable_type' => Vendor::class,
                    'auditable_id' => $vendor->id,
                    'action' => 'password_reset_completed',
                    'old_values' => null,
                    'new_values' => null,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);

                Event::dispatch(new PasswordReset($vendor));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('messages.vendor_password_reset_success'),
            ]);

            return redirect()->route('vendor.login');
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('messages.vendor_password_reset_failed'),
        ]);

        return back()->withErrors(['email' => __($status)]);
    }
}
