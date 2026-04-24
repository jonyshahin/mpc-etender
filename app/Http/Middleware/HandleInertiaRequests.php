<?php

namespace App\Http\Middleware;

use App\Models\VendorCategoryRequest;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $vendor = $request->user('vendor');
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? array_merge(
                    $user->only('id', 'name', 'email', 'language_pref'),
                    [
                        'role_slug' => $user->role?->slug,
                        // Only non-zero when the user can review — otherwise the
                        // sidebar badge never renders. Kept as 0 (not omitted)
                        // so the client-side type stays stable.
                        'pending_vendor_category_requests_count' => $user->hasPermission('vendors.review_category_requests')
                            ? VendorCategoryRequest::open()->count()
                            : 0,
                    ]
                ) : null,
                'vendor' => $vendor ? array_merge(
                    $vendor->only('id', 'company_name', 'email', 'prequalification_status', 'language_pref'),
                    [
                        'must_change_password' => (bool) $vendor->must_change_password,
                        'open_category_requests_count' => $vendor->categoryRequests()->open()->count(),
                    ]
                ) : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => app()->getLocale(),
            'dir' => in_array(app()->getLocale(), ['ar', 'ku'], true) ? 'rtl' : 'ltr',
            // Flash values surfaced from admin one-shot actions. `temporary_password`
            // is set by forceTemporaryPassword() and lives for exactly one request
            // so the admin detail page can open the copy-once modal.
            'flash' => [
                'temporary_password' => fn () => $request->session()->get('temporary_password'),
            ],
        ];
    }
}
