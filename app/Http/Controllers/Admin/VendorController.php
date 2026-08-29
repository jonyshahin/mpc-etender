<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVendorRequest;
use App\Http\Requests\Admin\VendorPrequalificationRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\SystemSetting;
use App\Models\Vendor;
use App\Services\FileUploadService;
use App\Services\PrintAssetService;
use App\Services\QrCodeService;
use App\Services\VendorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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
        private QrCodeService $qrCodeService,
        private PrintAssetService $printAssetService,
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

        // Land on the confirmation letter, which is the thing the admin actually
        // hands over: it renders the vendor's details and the one-shot temporary
        // password together, ready to print. The password cannot be recovered
        // afterwards (it is stored bcrypt-hashed), so this is the only moment it
        // can appear on the letter; later reprints omit it and the admin reissues
        // one from the detail page instead.
        // Bound to this vendor's id. A bare 'temporary_password' flash is global to
        // the session, so opening a *different* vendor's letter before navigating
        // away would print this vendor's password on it.
        return redirect()
            ->route('admin.vendors.confirmation', $vendor)
            ->with('vendor_temp_password', ['vendor_id' => $vendor->id, 'value' => $temp]);
    }

    public function show(Request $request, Vendor $vendor): Response
    {
        $vendor->load([
            'documents' => fn ($q) => $q->with('uploader:id,name')->orderByDesc('created_at'),
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
            // Gates the upload form and the approve/reject controls.
            'canReviewDocuments' => $request->user()->can('reviewDocuments', Vendor::class),
            'documentTypes' => DocumentType::options(),
        ]);
    }

    /**
     * Printable application-confirmation sheet for a vendor.
     *
     * Handed to the vendor as proof their application is on file. The QR points at
     * the public site rather than anything vendor-specific, so the sheet carries no
     * credential of its own — the temporary password below is the exception, and it
     * only appears immediately after onboarding.
     */
    public function confirmation(Request $request, Vendor $vendor): Response
    {
        $this->authorize('view', $vendor);

        $vendor->load('categories:id,name_en,name_ar');

        // Survive one more request so the PDF download from this page can include
        // the password too. Deliberately not passed through the URL — a query
        // string would put the credential in server logs, browser history and any
        // outbound referrer header.
        session()->keep('vendor_temp_password');

        return Inertia::render('admin/Vendors/Confirmation', array_merge(
            $this->confirmationData($vendor, $this->confirmationWebsiteUrl()),
            [
                // Present only when the caller arrived straight from onboarding this
                // exact vendor. Passwords are bcrypt-hashed, so there is no way to
                // recover one for a later reprint — and storing the plain text to
                // make that possible would be worse than the inconvenience.
                'temporaryPassword' => $this->temporaryPasswordFor($vendor),
                // Reissuing replaces the vendor's credential, so it needs `update`
                // rather than the `view` that opening the letter requires.
                'canReissuePassword' => $request->user()->can('update', $vendor),
            ],
        ));
    }

    /**
     * Downloadable PDF of the same sheet.
     *
     * Rendered from a Blade view rather than the React page because dompdf cannot
     * execute JavaScript. Note dompdf performs no Arabic shaping or bidi, so the
     * Arabic company name is deliberately omitted here — use the browser Print
     * button for an Arabic-faithful copy.
     */
    public function confirmationPdf(Vendor $vendor): HttpResponse
    {
        $this->authorize('view', $vendor);

        $vendor->load('categories:id,name_en,name_ar');

        // Re-arm the flash, exactly as confirmation() does. Without this the very
        // first download consumes it, and a second download — an admin wanting a
        // spare copy — silently produces a letter with the whole credentials block
        // missing and no indication anything was dropped.
        session()->keep('vendor_temp_password');

        $websiteUrl = $this->confirmationWebsiteUrl();
        $data = $this->confirmationData($vendor, $websiteUrl);

        $pdf = Pdf::loadView('pdf.vendor-confirmation', array_merge(
            $data,
            [
                // Raster rather than the page's vector QR: dompdf expands an SVG
                // QR into thousands of path operations and exhausts memory.
                'qrCode' => $this->qrCodeService->pngDataUri($websiteUrl),
                // Downscaled: dompdf embeds a raster at native resolution however
                // small it is drawn, and the full logo alone is ~518 KB.
                'logoSrc' => $this->printAssetService->logoForPdf($data['logoUrl']),
                'companyLogoSrc' => $data['companyLogoUrl']
                    ? $this->printAssetService->logoForPdf($data['companyLogoUrl'])
                    : null,
                // Read from the kept flash, never from the query string.
                'temporaryPassword' => $this->temporaryPasswordFor($vendor),
            ],
        ))->setPaper('a4');

        return $pdf->download(
            'vendor-confirmation-'.Str::slug($vendor->company_name).'.pdf'
        );
    }

    /**
     * The in-flight temporary password, but only if it was minted for THIS vendor.
     *
     * The flash lives in the admin's session, which is shared across every vendor
     * they look at — without the id check, onboarding vendor A and then opening
     * vendor B's letter would print A's password on B's document.
     */
    private function temporaryPasswordFor(Vendor $vendor): ?string
    {
        $flash = session('vendor_temp_password');

        if (! is_array($flash) || ($flash['vendor_id'] ?? null) !== $vendor->id) {
            return null;
        }

        return $flash['value'] ?? null;
    }

    /** Shared payload for the on-screen sheet and the PDF. */
    private function confirmationData(Vendor $vendor, string $websiteUrl): array
    {
        return [
            'vendor' => $vendor,
            'companyName' => SystemSetting::where('key', 'general.company_name')->value('value')
                ?: config('app.name'),
            'projectName' => SystemSetting::where('key', 'general.project_name')->value('value') ?: '',
            // Two marks, two meanings: Boulevard is the project, MPC is the
            // construction company delivering it. Prefer the official raster when
            // one has been dropped in; the committed SVG is a vector stand-in.
            'logoUrl' => file_exists(public_path('boulevard-logo.png'))
                ? '/boulevard-logo.png'
                : '/boulevard-logo.svg',
            'companyLogoUrl' => file_exists(public_path('mpc-logo.png'))
                ? '/mpc-logo.png'
                : null,
            'websiteUrl' => $websiteUrl,
            'qrCode' => $this->qrCodeService->svgDataUri($websiteUrl),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /** Configured public site, falling back to the app URL. */
    private function confirmationWebsiteUrl(): string
    {
        return SystemSetting::where('key', 'general.website_url')->value('value')
            ?: config('app.url')
            ?: url('/');
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

        $temp = $this->mintTemporaryPassword($request, $vendor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.vendor_temp_password_set'),
        ]);

        // Surfaced ONCE on the next request via flash bag — admin must copy
        // it on the detail page or it's gone.
        return back()->with('temporary_password', $temp);
    }

    /**
     * Mint a fresh temporary password and return to the confirmation letter with
     * it, so the whole document can be reprinted.
     *
     * A reprint is the only way to put a password back on the letter: what is
     * stored is a bcrypt hash, so the original is gone for good. Rotating is also
     * the right response on its own terms — an admin asking to reprint has
     * usually lost or mishandled the first copy, and that credential should stop
     * working. It does invalidate the previous password, so this is aimed at
     * vendors who have not signed in yet.
     */
    public function reissuePassword(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorize('update', $vendor);

        // Also require `view`, the permission the letter itself needs. Permissions
        // are synced freely on /admin/roles, so a role can hold update without
        // view — and this action would then rotate the credential and redirect
        // into a 403, discarding the new password with the old one already dead
        // and the vendor locked out.
        $this->authorize('view', $vendor);

        $temp = $this->mintTemporaryPassword($request, $vendor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('messages.vendor_password_reissued'),
        ]);

        return redirect()
            ->route('admin.vendors.confirmation', $vendor)
            ->with('vendor_temp_password', ['vendor_id' => $vendor->id, 'value' => $temp]);
    }

    /**
     * Replace the vendor's password with a new temporary one and record it.
     *
     * @return string The plain-text password — the only moment it exists
     */
    private function mintTemporaryPassword(Request $request, Vendor $vendor): string
    {
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

        return $temp;
    }
}
