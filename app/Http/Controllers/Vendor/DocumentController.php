<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\DocumentType;
use App\Enums\VendorDocStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\FileUploadRequest;
use App\Models\VendorDocument;
use App\Services\VendorDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(
        private VendorDocumentService $documentService,
    ) {}

    public function index(Request $request): Response
    {
        $vendor = $request->user('vendor');

        return Inertia::render('vendor/Documents/Index', [
            'documents' => $vendor->documents()
                ->select('id', 'document_type', 'title', 'file_path', 'file_size', 'mime_type', 'issue_date', 'expiry_date', 'status', 'review_notes', 'created_at')
                ->orderByDesc('created_at')
                ->get(),
            // Served from the enum so the picker cannot drift from what
            // validation accepts, as it had.
            'documentTypes' => DocumentType::options(),
        ]);
    }

    public function store(FileUploadRequest $request): RedirectResponse
    {
        $this->documentService->uploadByVendor(
            $request->user('vendor'),
            $request->file('file'),
            $request->validated(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document uploaded successfully.')]);

        return back();
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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document deleted successfully.')]);

        return back();
    }
}
