<?php

namespace App\Http\Controllers\Tender;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tender\StoreAddendumRequest;
use App\Models\Tender;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;

class AddendumController extends Controller
{
    public function __construct(
        private FileUploadService $fileUploadService,
    ) {}

    public function store(StoreAddendumRequest $request, Tender $tender): RedirectResponse
    {
        $data = $request->validated();

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $this->fileUploadService->upload(
                $request->file('file'),
                "tenders/{$tender->id}/addenda"
            );
        }

        $nextNumber = $tender->addenda()->max('addendum_number') + 1;

        $tender->addenda()->create([
            'issued_by' => $request->user()->id,
            'addendum_number' => $nextNumber,
            'subject' => $data['subject'],
            'content_en' => $data['content_en'],
            'content_ar' => $data['content_ar'] ?? null,
            'file_path' => $filePath,
            'extends_deadline' => $data['extends_deadline'],
            'new_deadline' => $data['extends_deadline'] ? $data['new_deadline'] : null,
            'published_at' => now(),
        ]);

        if ($data['extends_deadline'] && ! empty($data['new_deadline'])) {
            $tender->update(['submission_deadline' => $data['new_deadline']]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => __('Addendum issued.')]);
    }
}
