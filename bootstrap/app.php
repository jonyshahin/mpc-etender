<?php

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
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            LogAuditTrail::class,
        ]);

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
