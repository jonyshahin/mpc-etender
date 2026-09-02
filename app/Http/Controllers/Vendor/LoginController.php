<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Failed attempts allowed from one address-and-IP pair before it locks.
     *
     * Nothing throttled this route: no middleware on it, none registered
     * globally, and the staff side gets its throttling from Fortify — so this
     * endpoint alone allowed unlimited guesses against a known address, on a
     * portal whose accounts are admin-created with generated first passwords.
     */
    private const MAX_ATTEMPTS = 5;

    /** How long a locked key stays locked. */
    private const DECAY_SECONDS = 60;

    public function create(): Response
    {
        return Inertia::render('vendor/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $this->ensureIsNotRateLimited($request);

        if (! Auth::guard('vendor')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);

            return $this->rejectWithGenericError($request);
        }

        /** @var Vendor $vendor */
        $vendor = Auth::guard('vendor')->user();

        // Auth::attempt only ever checked the credentials. A suspended,
        // deactivated or blacklisted vendor authenticated fine and held a
        // session: they could not bid — BidPolicy checks is_active — but they
        // could still browse every tender, document and clarification their
        // categories reached, which is the sanction not being applied.
        if (! $vendor->mayAccessPortal()) {
            Auth::guard('vendor')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);

            // Deliberately the same message as a wrong password: whether an
            // address belongs to a suspended vendor is not something this form
            // should confirm.
            return $this->rejectWithGenericError($request);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        $vendor->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('vendor.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('vendor')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('vendor.login');
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => __('auth.vendor_throttled', [
                'seconds' => RateLimiter::availableIn($this->throttleKey($request)),
            ]),
        ]);
    }

    /**
     * One counter per address-and-IP pair.
     *
     * Keyed on both so a shared office IP cannot lock out a colleague, while a
     * single attacker still cannot walk one address from one machine.
     */
    private function throttleKey(Request $request): string
    {
        return 'vendor-login|'.Str::lower((string) $request->input('email')).'|'.$request->ip();
    }

    private function rejectWithGenericError(Request $request): RedirectResponse
    {
        return back()
            ->withErrors(['email' => __('auth.vendor_failed')])
            ->onlyInput('email');
    }
}
