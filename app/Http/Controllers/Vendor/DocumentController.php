<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorDocStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\FileUploadRequest;
use App\Models\VendorDocument;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(
        private FileUploadService $fileUploadService,
    ) {}

    public function index(Request $request): Response
    {
        $vendor = $request->user('vendor');

        return Inertia::render('vendor/Documents/Index', [
            'documents' => $vendor->documents()
                ->select('id', 'document_type', 'title', 'file_path', 'file_size', 'mime_type', 'issue_date', 'expiry_date', 'status', 'review_notes', 'created_at')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function store(FileUploadRequest $request): RedirectResponse
    {
        $vendor = $request->user('vendor');
        $data = $request->validated();
        $file = $request->file('file');

        $path = $this->fileUploadService->upload($file, "vendors/{$vendor->id}/documents", 'pdf,doc,docx,jpg,jpeg,png,xlsx');

        $vendor->documents()->create([
            'document_type' => $data['document_type'],
            'title' => $data['title'],
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'issue_date' => $data['issue_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'status' => VendorDocStatus::Pending,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document uploaded successfully.')]);

        return back();
    }

    public function destroy(Request $request, VendorDocument $document): RedirectResponse
    {
        $vendor = $request->user('vendor');

        if ($document->vendor_id !== $vendor->id) {
            abort(403);
        }

        if ($document->status !== VendorDocStatus::Pending) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Only pending documents can be deleted.')]);

            return back();
        }

        $this->fileUploadService->delete($document->file_path);
        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document deleted successfully.')]);

        return back();
    }
}
