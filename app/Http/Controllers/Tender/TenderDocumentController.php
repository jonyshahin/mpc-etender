<?php

namespace App\Http\Controllers\Tender;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tender\StoreTenderDocumentRequest;
use App\Models\Tender;
use App\Models\TenderDocument;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TenderDocumentController extends Controller
{
    public function __construct(
        private FileUploadService $fileUploadService,
    ) {}

    public function store(StoreTenderDocumentRequest $request, Tender $tender): RedirectResponse
    {
        $this->authorize('update', $tender);

        $data = $request->validated();
        $file = $request->file('file');

        $path = $this->fileUploadService->upload($file, "tenders/{$tender->id}/documents");

        // Handle versioning: mark existing docs of same title as not current
        $existingVersion = $tender->documents()
            ->where('title', $data['title'])
            ->where('is_current', true)
            ->first();

        $version = 1;
        if ($existingVersion) {
            $existingVersion->update(['is_current' => false]);
            $version = $existingVersion->version + 1;
        }

        $tender->documents()->create([
            'uploaded_by' => $request->user()->id,
            'title' => $data['title'],
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'doc_type' => $data['doc_type'],
            'version' => $version,
            'is_current' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document uploaded.')]);

        return back();
    }

    public function destroy(Tender $tender, TenderDocument $doc): RedirectResponse
    {
        $this->authorize('update', $tender);

        $this->fileUploadService->delete($doc->file_path);
        $doc->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document deleted.')]);

        return back();
    }
}
