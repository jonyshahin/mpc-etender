<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorProfileUpdateRequest;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * The fields the profile form owns.
     *
     * Named explicitly rather than passing the model: $hidden covers the
     * password and remember token, so handing over $request->user('vendor')
     * whole also handed over rejection_reason (an MPC reviewer's internal note),
     * qualified_by (an internal user id), and the is_active /
     * must_change_password account flags — none of which the page renders.
     */
    private const EDITABLE = [
        'company_name',
        'company_name_ar',
        'trade_license_no',
        'contact_person',
        'email',
        'phone',
        'whatsapp_number',
        'address',
        'city',
        'country',
        'website',
        'language_pref',
    ];

    public function edit(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->user('vendor');

        return Inertia::render('vendor/Profile', [
            'vendor' => $vendor->only('id', ...self::EDITABLE),
            // Read-only context for the standing panel. The vendor already sees
            // their own status on the dashboard; repeating it here saves a trip
            // back when the reason they opened the page was a rejection.
            'standing' => [
                'prequalification_status' => $vendor->prequalification_status,
                'qualified_at' => $vendor->qualified_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(VendorProfileUpdateRequest $request): RedirectResponse
    {
        $request->user('vendor')->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.vendor_profile_updated')]);

        return back();
    }
}
