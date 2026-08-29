<?php

namespace App\Services;

use App\Enums\VendorDocStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Prequalification documents on a vendor's file.
 *
 * Two routes in: the vendor uploads through the portal, or an MPC user files a
 * copy the vendor handed over in person. Both land in the same table and the
 * same S3 prefix so nothing downstream has to care which happened — the
 * difference is recorded on the row (`uploaded_by`) and in the audit trail
 * rather than in where the file lives.
 */
class VendorDocumentService
{
    public function __construct(private FileUploadService $fileUploadService) {}

    /** Where a vendor's documents live in the bucket, whoever uploaded them. */
    private function storagePath(Vendor $vendor): string
    {
        return "vendors/{$vendor->id}/documents";
    }

    /**
     * A document the vendor uploaded themselves. Lands unreviewed.
     *
     * @param  array{document_type: string, title: string, issue_date?: ?string, expiry_date?: ?string}  $data
     */
    public function uploadByVendor(Vendor $vendor, UploadedFile $file, array $data): VendorDocument
    {
        return $this->store($vendor, $file, $data, [
            'status' => VendorDocStatus::Pending,
        ], null, 'vendor_document_uploaded');
    }

    /**
     * A document an MPC user files on the vendor's behalf.
     *
     * Recorded as approved: the admin received the paperwork directly, so the
     * act of filing it is the verification. `uploaded_by` and `reviewed_by`
     * both name them, which is what distinguishes this from a vendor upload
     * that an admin approved afterwards.
     *
     * @param  array{document_type: string, title: string, issue_date?: ?string, expiry_date?: ?string}  $data
     */
    public function uploadForVendor(Vendor $vendor, UploadedFile $file, array $data, User $admin): VendorDocument
    {
        return $this->store($vendor, $file, $data, [
            'status' => VendorDocStatus::Approved,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ], $admin, 'vendor_document_filed_by_admin');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $reviewState
     */
    private function store(
        Vendor $vendor,
        UploadedFile $file,
        array $data,
        array $reviewState,
        ?User $actor,
        string $action,
    ): VendorDocument {
        // Outside the transaction: the upload is the slow, external part, and
        // holding a database transaction open across it buys nothing. A file
        // orphaned by a failed insert costs storage, not correctness.
        $path = $this->fileUploadService->upload($file, $this->storagePath($vendor), 'pdf');

        return DB::transaction(function () use ($vendor, $file, $data, $reviewState, $actor, $action, $path) {
            $document = $vendor->documents()->create([
                'uploaded_by' => $actor?->id,
                'document_type' => $data['document_type'],
                'title' => $data['title'],
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'issue_date' => $data['issue_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                ...$reviewState,
            ]);

            $this->audit($document, $actor, $action, null, [
                'title' => $document->title,
                'document_type' => $document->document_type->value,
                'status' => $document->status->value,
            ]);

            return $document;
        });
    }

    /** Accept a document the vendor uploaded. */
    public function approve(VendorDocument $document, User $reviewer, ?string $notes = null): VendorDocument
    {
        return $this->review($document, $reviewer, VendorDocStatus::Approved, $notes, 'vendor_document_approved');
    }

    /** Reject a document. The reason is shown to the vendor on their documents page. */
    public function reject(VendorDocument $document, User $reviewer, string $reason): VendorDocument
    {
        return $this->review($document, $reviewer, VendorDocStatus::Rejected, $reason, 'vendor_document_rejected');
    }

    private function review(
        VendorDocument $document,
        User $reviewer,
        VendorDocStatus $status,
        ?string $notes,
        string $action,
    ): VendorDocument {
        // Read before update(): Eloquent resyncs $original on save, so
        // getOriginal() afterwards returns the value just written.
        $previous = $document->status->value;

        return DB::transaction(function () use ($document, $reviewer, $status, $notes, $action, $previous) {
            $document->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $this->audit(
                $document,
                $reviewer,
                $action,
                ['status' => $previous],
                ['status' => $status->value, 'review_notes' => $notes],
            );

            return $document->refresh();
        });
    }

    /**
     * Remove a document from the vendor's file.
     *
     * The stored object goes with it — an orphaned file in the bucket is still
     * reachable by anyone holding a previously issued temporary URL.
     */
    public function delete(VendorDocument $document, ?User $actor = null): void
    {
        $snapshot = [
            'title' => $document->title,
            'document_type' => $document->document_type->value,
            'status' => $document->status->value,
        ];
        $path = $document->file_path;

        DB::transaction(function () use ($document, $actor, $snapshot) {
            $this->audit($document, $actor, 'vendor_document_deleted', $snapshot, null);
            $document->delete();
        });

        $this->fileUploadService->delete($path);
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(VendorDocument $document, ?User $actor, string $action, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => $actor?->id,
            // Scoped so the vendor detail page can show its own document history.
            'vendor_id' => $document->vendor_id,
            'auditable_type' => VendorDocument::class,
            'auditable_id' => $document->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
