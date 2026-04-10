<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\VendorRegistrationRequest;
use App\Models\Category;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(
        private VendorService $vendorService,
    ) {}

    public function create(): Response
    {
        return Inertia::render('vendor/Register', [
            'categories' => Category::active()
                ->roots()
                ->with('children:id,name_en,name_ar,parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'name_en', 'name_ar', 'parent_id']),
        ]);
    }

    public function store(VendorRegistrationRequest $request): RedirectResponse
    {
        $vendor = $this->vendorService->register($request->validated());

        Auth::guard('vendor')->login($vendor);

        return redirect()->route('vendor.dashboard')
            ->with('flash', ['type' => 'success', 'message' => __('Registration successful. Your application is under review.')]);
    }
}
