<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale');

        if (! $locale) {
            if ($user = $request->user()) {
                $locale = $user->language_pref;
            } elseif ($vendor = $request->user('vendor')) {
                $locale = $vendor->language_pref;
            }
        }

        if ($locale && in_array($locale, ['en', 'ar', 'ku'])) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
