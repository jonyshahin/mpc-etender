<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('vendor/Profile', [
            'vendor' => $request->user('vendor'),
        ]);
    }

    public function update(VendorProfileUpdateRequest $request): RedirectResponse
    {
        $request->user('vendor')->update($request->validated());

        return back()->with('flash', ['type' => 'success', 'message' => __('Profile updated successfully.')]);
    }
}
