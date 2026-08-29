<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VendorDocumentReviewRequest;
use App\Http\Requests\Admin\VendorDocumentUploadRequest;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Services\VendorDocumentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Prequalification documents, from the MPC side of the desk.
 *
 * Vendors are onboarded by admins rather than registering themselves, so most
 * paperwork arrives by hand or email and never passes through the vendor
 * portal. These routes are how it gets on file.
 */
class VendorDocumentController extends Controller
{
    public function __construct(private VendorDocumentService $documentService) {}

    /**
     * Reject any document that does not belong to the vendor in the URL.
     *
     * Both are route-model-bound independently, so without this check
     * /admin/vendors/{A}/documents/{document-of-B} would resolve and act on
     * B's file while every authorization check passed against A.
     */
    private function assertBelongsTo(Vendor $vendor, VendorDocument $document): void
    {
        abort_unless($document->vendor_id === $vendor->id, 404);
    }

    public function store(VendorDocumentUploadRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->documentService->uploadForVendor(
            $vendor,
            $request->file('file'),
            $request->validated(),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document filed against the vendor record.')]);

        return back();
    }

    public function approve(VendorDocumentReviewRequest $request, Vendor $vendor, VendorDocument $document): RedirectResponse
    {
        $this->assertBelongsTo($vendor, $document);

        $this->documentService->approve($document, $request->user(), $request->input('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document approved.')]);

        return back();
    }

    public function reject(VendorDocumentReviewRequest $request, Vendor $vendor, VendorDocument $document): RedirectResponse
    {
        $this->assertBelongsTo($vendor, $document);

        // Required here rather than in the request class: the same class serves
        // approve(), where a note is optional.
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->documentService->reject($document, $request->user(), $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document rejected.')]);

        return back();
    }

    public function destroy(VendorDocumentReviewRequest $request, Vendor $vendor, VendorDocument $document): RedirectResponse
    {
        $this->assertBelongsTo($vendor, $document);

        $this->documentService->delete($document, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document removed.')]);

        return back();
    }
}
