<?php

namespace App\Http\Controllers\Tender;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tender\StoreAddendumRequest;
use App\Models\AuditLog;
use App\Models\Tender;
use App\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

class AddendumController extends Controller
{
    public function __construct(
        private FileUploadService $fileUploadService,
    ) {}

    /**
     * Issue an addendum on a published tender.
     *
     * BUG-26: when extends_deadline is true, both the tender's
     * submission_deadline AND opening_date must move — leaving
     * opening_date untouched produced un-openable tenders
     * (submission_deadline >= opening_date). The whole write is
     * wrapped in DB::transaction so a partial failure can't recreate
     * the bug state we're fixing. Audit log captures both columns'
     * old/new values; old values must be read before the update().
     */
    public function store(StoreAddendumRequest $request, Tender $tender): RedirectResponse
    {
        $data = $request->validated();

        $filePath = null;
        if ($request->hasFile('file')) {
            // Note: FileUploadService writes to S3 outside the transaction
            // (TECH-DEBT-06). If the DB transaction below rolls back, the
            // file is left orphaned — same trade-off as BUG-22 fix path.
            $filePath = $this->fileUploadService->upload(
                $request->file('file'),
                "tenders/{$tender->id}/addenda"
            );
        }

        try {
            DB::transaction(function () use ($tender, $data, $filePath, $request) {
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
                    'new_opening_date' => $data['extends_deadline'] ? $data['new_opening_date'] : null,
                    'published_at' => now(),
                ]);

                if ($data['extends_deadline']) {
                    // Capture old values BEFORE update — Eloquent's
                    // getOriginal() returns post-save values once update()
                    // has run (BUG-08 / TECH-DEBT-03 territory).
                    $oldSubmissionDeadline = $tender->submission_deadline?->toIso8601String();
                    $oldOpeningDate = $tender->opening_date?->toIso8601String();

                    $tender->update([
                        'submission_deadline' => $data['new_deadline'],
                        'opening_date' => $data['new_opening_date'],
                    ]);

                    AuditLog::create([
                        'user_id' => auth()->id(),
                        'auditable_type' => Tender::class,
                        'auditable_id' => $tender->id,
                        'action' => 'addendum_extends_deadline',
                        'old_values' => [
                            'submission_deadline' => $oldSubmissionDeadline,
                            'opening_date' => $oldOpeningDate,
                        ],
                        'new_values' => [
                            'submission_deadline' => $data['new_deadline'],
                            'opening_date' => $data['new_opening_date'],
                        ],
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'created_at' => now(),
                    ]);
                }
            });
        } catch (Throwable $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('messages.addendum.failed'),
            ]);

            return back();
        }

        $messageKey = $data['extends_deadline']
            ? 'messages.addendum.issued_with_deadline_extension'
            : 'messages.addendum.issued';

        Inertia::flash('toast', ['type' => 'success', 'message' => __($messageKey)]);

        return back();
    }
}
