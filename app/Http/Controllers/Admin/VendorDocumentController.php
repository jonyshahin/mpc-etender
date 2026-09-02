<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VendorDocumentReviewRequest;
use App\Http\Requests\Admin\VendorDocumentUploadRequest;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Services\FileUploadService;
use App\Services\VendorDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prequalification documents, from the MPC side of the desk.
 *
 * Vendors are onboarded by admins rather than registering themselves, so most
 * paperwork arrives by hand or email and never passes through the vendor
 * portal. These routes are how it gets on file.
 */
class VendorDocumentController extends Controller
{
    public function __construct(
        private VendorDocumentService $documentService,
        private FileUploadService $fileUploadService,
    ) {}

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

    /**
     * Stream a vendor's document to the reviewing MPC user.
     *
     * The vendor detail page used to receive a pre-signed S3 URL for every
     * document on the file, minted on page load whether or not anyone opened
     * one, at the 30-minute default, and recorded nowhere. Going through the
     * app means the bucket URL never leaves the server and the access lands in
     * document_access_logs, which CLAUDE.md requires.
     */
    public function download(Request $request, Vendor $vendor, VendorDocument $document): StreamedResponse
    {
        $this->authorize('view', $vendor);
        $this->assertBelongsTo($vendor, $document);

        $this->fileUploadService->logAccess(
            documentType: VendorDocument::class,
            documentId: $document->id,
            action: 'downloaded',
            userId: $request->user()->id,
            // Both columns: the log answers "who touched this" and "whose file
            // was it" with one row.
            vendorId: $vendor->id,
        );

        return Storage::disk('s3')->download($document->file_path, $document->title.'.pdf');
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
