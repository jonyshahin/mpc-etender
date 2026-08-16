<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVendorRequest;
use App\Http\Requests\Admin\VendorPrequalificationRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Vendor;
use App\Services\FileUploadService;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function __construct(
        private VendorService $vendorService,
        private FileUploadService $fileUploadService,
    ) {}

    public function index(Request $request): Response
    {
        $query = Vendor::with('categories:id,name_en')
            ->select('id', 'company_name', 'email', 'prequalification_status', 'qualified_at', 'city', 'country', 'created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('prequalification_status', $status);
        }

        if ($categoryId = $request->input('category_id')) {
            $query->inCategory($categoryId);
        }

        $query->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'));

        return Inertia::render('admin/Vendors/Index', [
            'vendors' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only('search', 'status', 'category_id', 'sort', 'direction'),
            // Feeds the "Add Vendor" dialog's category tree.
            'categories' => Category::active()
                ->roots()
                ->with('children:id,name_en,name_ar,parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'name_en', 'name_ar', 'parent_id']),
            'canCreate' => $request->user()->can('create', Vendor::class),
        ]);
    }

    /**
     * Onboard a vendor. This replaced public self-registration — admins are now
     * the only way a vendor account comes into existence.
     */
    public function store(StoreVendorRequest $request): RedirectResponse
    {
        $temp = Str::password(12);

        $vendor = $this->vendorService->createByAdmin(
            $request->validated(),
            $request->user(),
            $temp,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.vendor_created', ['company' => $vendor->company_name]),
        ]);

        // Land on the detail page so the one-shot temporary password renders
        // there — same flash contract as forceTemporaryPassword(). The admin
        // must hand it to the vendor now or reset it later.
        return redirect()
            ->route('admin.vendors.show', $vendor)
            ->with('temporary_password', $temp);
    }

    public function show(Vendor $vendor): Response
    {
        $vendor->load([
            'documents' => fn ($q) => $q->orderByDesc('created_at'),
            'categories:id,name_en,name_ar',
            'qualifiedBy:id,name',
        ]);

        $documentUrls = [];
        foreach ($vendor->documents as $doc) {
            $documentUrls[$doc->id] = $this->fileUploadService->getTemporaryUrl($doc->file_path);
        }

        return Inertia::render('admin/Vendors/Show', [
            'vendor' => $vendor,
            'documentUrls' => $documentUrls,
        ]);
    }

    public function prequalify(VendorPrequalificationRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->vendorService->prequalify($vendor, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor approved successfully.')]);

        return back();
    }

    public function reject(VendorPrequalificationRequest $request, Vendor $vendor): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->vendorService->reject($vendor, $request->user(), $request->input('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor rejected.')]);

        return back();
    }

    public function suspend(VendorPrequalificationRequest $request, Vendor $vendor): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->vendorService->suspend($vendor, $request->user(), $request->input('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor suspended.')]);

        return back();
    }

    public function sendPasswordReset(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('update', $vendor);

        // Admin already knows the vendor exists — unlike the guest flow we
        // should surface real failures (throttle, mail driver error, etc.)
        // instead of lying about a successful send.
        $status = Password::broker('vendors')->sendResetLink(['email' => $vendor->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('messages.vendor_password_reset_send_failed', [
                    'reason' => __($status),
                ]),
            ]);

            return back();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'vendor_id' => $vendor->id,
            'auditable_type' => Vendor::class,
            'auditable_id' => $vendor->id,
            'action' => 'password_reset_admin_sent',
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.vendor_password_reset_sent', ['email' => $vendor->email]),
        ]);

        return back();
    }

    public function forceTemporaryPassword(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('update', $vendor);

        $temp = Str::password(12);

        $vendor->update([
            'password' => Hash::make($temp),
            'must_change_password' => true,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'vendor_id' => $vendor->id,
            'auditable_type' => Vendor::class,
            'auditable_id' => $vendor->id,
            'action' => 'password_reset_admin_temp',
            'old_values' => null,
            'new_values' => ['must_change_password' => true],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.vendor_temp_password_set'),
        ]);

        // Surfaced ONCE on the next request via flash bag — admin must copy
        // it on the detail page or it's gone.
        return back()->with('temporary_password', $temp);
    }
}
