<?php

namespace App\Http\Controllers\Vendor\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('vendor/ChangePassword', [
            'mustChangePassword' => (bool) $request->user('vendor')->must_change_password,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:vendor'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        /** @var Vendor $vendor */
        $vendor = $request->user('vendor');

        $vendor->forceFill([
            'password' => Hash::make($request->input('password')),
            'must_change_password' => false,
        ])->save();

        AuditLog::create([
            'user_id' => null,
            'vendor_id' => $vendor->id,
            'auditable_type' => Vendor::class,
            'auditable_id' => $vendor->id,
            'action' => 'password_changed',
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.vendor_password_changed'),
        ]);

        return redirect()->route('vendor.dashboard');
    }
}
