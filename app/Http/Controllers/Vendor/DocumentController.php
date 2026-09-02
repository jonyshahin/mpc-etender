<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\DocumentType;
use App\Enums\VendorDocStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\FileUploadRequest;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Services\FileUploadService;
use App\Services\VendorDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(
        private VendorDocumentService $documentService,
        private FileUploadService $files,
    ) {}

    public function index(Request $request): Response
    {
        /** @var Vendor $vendor */
        $vendor = $request->user('vendor');

        $documents = $vendor->documents()
            // file_path is deliberately absent: it is the raw S3 object key,
            // and the page has a signed download route to reach the file with.
            ->select('id', 'document_type', 'title', 'file_size', 'mime_type', 'issue_date', 'expiry_date', 'status', 'review_notes', 'reviewed_at', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (VendorDocument $document) => [
                ...$document->only(
                    'id', 'document_type', 'title', 'file_size', 'mime_type',
                    'issue_date', 'expiry_date', 'status', 'review_notes',
                ),
                'reviewed_at' => $document->reviewed_at?->toIso8601String(),
                'created_at' => $document->created_at->toIso8601String(),
                'download_url' => route('vendor.documents.download', $document->id),
            ]);

        return Inertia::render('vendor/Documents/Index', [
            'documents' => $documents,
            // Served from the enum so the picker cannot drift from what
            // validation accepts, as it had.
            'documentTypes' => DocumentType::options(),
            'summary' => $this->summary($vendor),
        ]);
    }

    public function store(FileUploadRequest $request): RedirectResponse
    {
        $this->documentService->uploadByVendor(
            $request->user('vendor'),
            $request->file('file'),
            $request->validated(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.vendor_document_uploaded')]);

        return back();
    }

    /**
     * Hand back a short-lived link to the vendor's own document.
     *
     * There was no way for a vendor to retrieve a document they had uploaded:
     * the list rendered a title and a size, and the storage key it shipped
     * alongside them was unusable from the browser. Reads go through
     * FileUploadService so this lands in document_access_logs like every other
     * document read in the system.
     */
    public function download(Request $request, VendorDocument $document): RedirectResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->user('vendor');

        abort_unless($document->vendor_id === $vendor->id, 403);

        $this->files->logAccess(
            documentType: VendorDocument::class,
            documentId: $document->id,
            action: 'downloaded',
            vendorId: $vendor->id,
        );

        return redirect()->away($this->files->getTemporaryUrl($document->file_path, 10));
    }

    public function destroy(Request $request, VendorDocument $document): RedirectResponse
    {
        $vendor = $request->user('vendor');

        if ($document->vendor_id !== $vendor->id) {
            abort(403);
        }

        // Once reviewed, the decision is part of the vendor's record — removing
        // the file would leave an approval pointing at nothing.
        if ($document->status !== VendorDocStatus::Pending) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Only pending documents can be deleted.')]);

            return back();
        }

        $this->documentService->delete($document);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.vendor_document_deleted')]);

        return back();
    }

    /**
     * The four figures the page leads with.
     *
     * @return array<string, int>
     */
    private function summary(Vendor $vendor): array
    {
        $byStatus = $vendor->documents()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'approved' => (int) ($byStatus[VendorDocStatus::Approved->value] ?? 0),
            'pending' => (int) ($byStatus[VendorDocStatus::Pending->value] ?? 0),
            'rejected' => (int) ($byStatus[VendorDocStatus::Rejected->value] ?? 0),
            // Approved-or-pending only, matching the dashboard: a rejected
            // document is not a credential whose expiry means anything.
            'expiring' => $vendor->documents()
                ->whereIn('status', [VendorDocStatus::Pending, VendorDocStatus::Approved])
                ->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [now(), now()->addDays(30)])
                ->count(),
            'expired' => $vendor->documents()
                ->whereIn('status', [VendorDocStatus::Pending, VendorDocStatus::Approved])
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<', now())
                ->count(),
        ];
    }
}
