<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureProjectAccess;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceVendorPasswordChange;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogAuditTrail;
use App\Http\Middleware\SetLocale;
use App\Rules\PdfFile;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            // Ahead of everything that reads the current user, so a withdrawn
            // account is turned out before any of it runs.
            EnsureAccountIsActive::class,
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            LogAuditTrail::class,
        ]);

        // Both directions in one call. redirectUsersTo() is redirectTo(users:)
        // with guests defaulting to null — and redirectTo() wraps a null guests
        // in `fn () => null`, which is truthy, so calling it after
        // redirectGuestsTo() silently replaces the guest redirect with nothing
        // and every protected page answers 401.
        $middleware->redirectTo(
            // Where `auth` sends someone who is not signed in. Laravel's default
            // is route('login') whichever guard refused them, so the vendor
            // portal sent vendors to the MPC staff form — on logout with a
            // lapsed session, and on any vendor page opened after one ended.
            //
            // Staff routes lead to the portal too rather than to route('login'):
            // the staff sign-in sits behind STAFF_AUTH_PREFIX to keep it off the
            // public site, and redirecting guests to it would hand the path to
            // anyone who opened a single staff URL. Staff whose session lapses
            // land on the vendor form and go to their own bookmark.
            guests: fn () => route('vendor.login'),
            // Where `guest` sends someone who *is* signed in. The default is
            // route('dashboard') — the staff dashboard — for every guard, so a
            // signed-in vendor who opened the vendor sign-in page was sent
            // there, refused, and forwarded to the staff form: production shows
            // a vendor spending four minutes typing correct credentials into a
            // form that checks the staff table. With guests now sent to the
            // portal, the same path would loop. Vendor routes send vendors home.
            users: fn (Request $request) => $request->routeIs('vendor.*')
                ? route('vendor.dashboard')
                : route('dashboard'),
        );

        $middleware->alias([
            'project.access' => EnsureProjectAccess::class,
            'role' => EnsureUserHasRole::class,
            'vendor.password.required' => ForceVendorPasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Once post_max_size is exceeded PHP discards the request body before
        // Laravel sees it, so no field rule can catch this and the framework
        // raises a bare 413 with nothing on it. Say what actually happened.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $message = __('bid.documents.file_too_large', ['size' => PdfFile::maxLabel()]);

            // ValidatePostSize sits in the global stack, ahead of the session,
            // so withErrors() is not always available at this point.
            if ($request->hasSession()) {
                return back()->withErrors(['file' => $message]);
            }

            return response($message, 413);
        });
    })->create();
