<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\FiltersLists;
use App\Enums\DocumentType;
use App\Enums\VendorDocStatus;
use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVendorRequest;
use App\Http\Requests\Admin\UpdateVendorRequest;
use App\Http\Requests\Admin\VendorPrequalificationRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\SystemSetting;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Services\PrintAssetService;
use App\Services\QrCodeService;
use App\Services\VendorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    use FiltersLists;

    public function __construct(
        private VendorService $vendorService,
        private QrCodeService $qrCodeService,
        private PrintAssetService $printAssetService,
    ) {}

    /**
     * Columns the vendor list may be ordered by.
     *
     * A whitelist because orderBy() does not check the column: Laravel
     * validates the direction and throws on anything but asc/desc, but hands
     * the column straight to the grammar, so `?sort=nope` was a 500.
     */
    private const SORTABLE = [
        'company_name',
        'email',
        'prequalification_status',
        'city',
        'created_at',
    ];

    public function index(Request $request): Response
    {
        $search = $this->searchTerm($request);
        $status = $this->filterValue($request, 'status');
        $categoryId = $this->filterValue($request, 'category_id');

        // Everything except the status filter, so the status counts can be
        // taken from the same scope the rows are drawn from.
        $base = fn () => Vendor::query()
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('company_name', 'like', "%{$search}%")
                    ->orWhere('company_name_ar', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('trade_license_no', 'like', "%{$search}%");
            }))
            ->when($categoryId, fn ($q) => $q->inCategory($categoryId));

        $sort = in_array($request->input('sort'), self::SORTABLE, true)
            ? $request->input('sort')
            : 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $vendors = $base()
            ->with('categories:id,name_en,name_ar')
            // select() before withCount(): it replaces the select list, so
            // calling it afterwards drops the count subqueries and the row
            // arrives without documents_count at all.
            ->select('id', 'company_name', 'company_name_ar', 'email', 'prequalification_status', 'qualified_at', 'city', 'country', 'created_at')
            ->withCount(['documents', 'bids'])
            ->when($status, fn ($q) => $q->where('prequalification_status', $status))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/Vendors/Index', [
            'vendors' => $vendors,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $status,
                'category_id' => $categoryId,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusCounts' => $this->vendorStatusCounts($base()),
            'summary' => $this->vendorSummary($base()),
            // Feeds both the category filter and the "Add Vendor" dialog.
            'categories' => Category::active()
                ->roots()
                ->with('children:id,name_en,name_ar,parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'name_en', 'name_ar', 'parent_id']),
            'canCreate' => $request->user()->can('create', Vendor::class),
        ]);
    }

    /**
     * How many vendors sit at each prequalification status, every status present.
     *
     * @return array<string, int>
     */
    private function vendorStatusCounts(Builder $query): array
    {
        $counts = $query->select('prequalification_status', DB::raw('COUNT(*) as count'))
            ->groupBy('prequalification_status')
            ->pluck('count', 'prequalification_status');

        return collect(VendorStatus::cases())
            ->mapWithKeys(fn (VendorStatus $case) => [$case->value => (int) ($counts[$case->value] ?? 0)])
            ->all();
    }

    /**
     * Headline figures, on the same scope as the rows beneath them.
     *
     * @return array<string, int>
     */
    private function vendorSummary(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'qualified' => (clone $query)->where('prequalification_status', VendorStatus::Qualified)->count(),
            // Pending and under review are one queue to whoever works it.
            'awaiting_review' => (clone $query)
                ->whereIn('prequalification_status', [VendorStatus::Pending, VendorStatus::UnderReview])
                ->count(),
            'documents_pending' => (clone $query)
                ->whereHas('documents', fn ($q) => $q->where('status', VendorDocStatus::Pending))
                ->count(),
        ];
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

    /**
     * Correct a vendor's details.
     *
     * The only way to change them: vendors are onboarded by admins and the
     * portal gives a vendor no way to edit their own company record, so a typo
     * in a licence number or a changed contact person had nowhere to go.
     */
    public function update(UpdateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->vendorService->updateByAdmin($vendor, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vendor updated successfully.')]);

        return back();
    }

    public function show(Request $request, Vendor $vendor): Response
    {
        // The only read method on this controller that did not authorize.
        // confirmation() and confirmationPdf() below both do, and they disclose
        // strictly less than this page — so an admin-role user without
        // vendors.view was refused the confirmation sheet and handed the whole
        // record here.
        $this->authorize('view', $vendor);

        $vendor->load([
            'documents' => fn ($q) => $q->with('uploader:id,name')->orderByDesc('created_at'),
            'categories:id,name_en,name_ar',
            'qualifiedBy:id,name',
        ]);

        // No file_path, and no pre-signed URLs. The page used to receive a
        // temporary S3 link for every document on the file, minted on load
        // whether or not anyone opened one and recorded nowhere; file_path rode
        // along beside them, typed on the React side and never rendered. Both
        // are replaced by a route that streams and logs.
        $vendor->setRelation('documents', $vendor->documents->map(fn (VendorDocument $doc) => [
            'id' => $doc->id,
            'title' => $doc->title,
            'document_type' => $doc->document_type->value,
            'document_type_label_key' => $doc->document_type->labelKey(),
            'status' => $doc->status->value,
            'status_label_key' => $doc->status->labelKey(),
            'file_size' => $doc->file_size,
            'issue_date' => $doc->issue_date?->toDateString(),
            'expiry_date' => $doc->expiry_date?->toDateString(),
            'review_notes' => $doc->review_notes,
            'reviewed_at' => $doc->reviewed_at,
            'created_at' => $doc->created_at,
            'uploader' => $doc->uploader ? ['id' => $doc->uploader->id, 'name' => $doc->uploader->name] : null,
            'download_url' => route('admin.vendors.documents.download', [$vendor, $doc]),
        ]));

        return Inertia::render('admin/Vendors/Show', [
            'vendor' => $vendor,
            // Gates the upload form and the approve/reject controls.
            'canReviewDocuments' => $request->user()->can('reviewDocuments', Vendor::class),
            'documentTypes' => DocumentType::options(),
            // Gates the Edit button and feeds the form it opens.
            'canUpdate' => $request->user()->can('update', $vendor),
            'categories' => Category::active()
                ->roots()
                ->with('children:id,name_en,name_ar,parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'name_en', 'name_ar', 'parent_id']),
            'vendorCategoryIds' => $vendor->categories->pluck('id'),
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
     * execute JavaScript. dompdf also does no bidi reordering and no Arabic
     * shaping, so the template runs every user-supplied string through
     * ArabicTextService first — without it, Arabic prints reversed and in
     * disconnected letters.
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
