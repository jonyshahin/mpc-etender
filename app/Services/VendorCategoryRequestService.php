<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategoryRequest;
use App\Notifications\VendorCategoryRequestApproved;
use App\Notifications\VendorCategoryRequestRejected;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns every state transition for vendor category change requests.
 *
 * State machine:
 *   pending → under_review → approved / rejected
 *            → withdrawn (vendor-initiated, only from pending/under_review)
 *
 * Callers (controllers) validate + authorize; all persistence, audit writes,
 * and notification dispatch live here so the admin-queue and vendor-portal
 * code paths stay thin.
 */
class VendorCategoryRequestService
{
    public function __construct(
        private FileUploadService $files,
    ) {}

    /**
     * Vendor submits a new category change request.
     *
     * @param  array<int, string>  $addCategoryIds
     * @param  array<int, string>  $removeCategoryIds
     * @param  array<int, UploadedFile>  $evidenceFiles
     */
    public function submit(
        Vendor $vendor,
        string $justification,
        array $addCategoryIds,
        array $removeCategoryIds,
        array $evidenceFiles,
    ): VendorCategoryRequest {
        if (empty($addCategoryIds) && empty($removeCategoryIds)) {
            throw ValidationException::withMessages([
                'categories' => __('validation.vendor_category_request.empty_delta'),
            ]);
        }

        // Filter out no-ops: adds the vendor already has, removes they don't have.
        $currentIds = $vendor->categories()->pluck('categories.id')->all();
        $addCategoryIds = array_values(array_diff($addCategoryIds, $currentIds));
        $removeCategoryIds = array_values(array_intersect($removeCategoryIds, $currentIds));

        if (empty($addCategoryIds) && empty($removeCategoryIds)) {
            throw ValidationException::withMessages([
                'categories' => __('validation.vendor_category_request.no_net_change'),
            ]);
        }

        if ($vendor->categoryRequests()->open()->exists()) {
            throw ValidationException::withMessages([
                'categories' => __('validation.vendor_category_request.already_pending'),
            ]);
        }

        return DB::transaction(function () use ($vendor, $justification, $addCategoryIds, $removeCategoryIds, $evidenceFiles) {
            $req = $vendor->categoryRequests()->create([
                'justification' => $justification,
                'status' => 'pending',
            ]);

            foreach ($addCategoryIds as $id) {
                $req->items()->create(['category_id' => $id, 'operation' => 'add']);
            }
            foreach ($removeCategoryIds as $id) {
                $req->items()->create(['category_id' => $id, 'operation' => 'remove']);
            }

            foreach ($evidenceFiles as $file) {
                $path = $this->files->upload($file, "vendor-category-requests/{$req->id}");
                $req->evidence()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by_vendor_id' => $vendor->id,
                ]);
            }

            AuditLog::create([
                'user_id' => null,
                'vendor_id' => $vendor->id,
                'auditable_type' => VendorCategoryRequest::class,
                'auditable_id' => $req->id,
                'action' => 'vendor_category_request_submitted',
                'old_values' => null,
                'new_values' => [
                    'vendor_id' => $vendor->id,
                    'add_count' => count($addCategoryIds),
                    'remove_count' => count($removeCategoryIds),
                    'evidence_count' => count($evidenceFiles),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return $req->fresh(['items.category', 'evidence']);
        });
    }

    /**
     * Reviewer picks up a pending request. Idempotent — no-op if already
     * under review or terminal.
     */
    public function startReview(VendorCategoryRequest $req, User $reviewer): VendorCategoryRequest
    {
        if ($req->status !== 'pending') {
            return $req;
        }

        $req->update([
            'status' => 'under_review',
            'reviewed_by' => $reviewer->id,
        ]);

        AuditLog::create([
            'user_id' => $reviewer->id,
            'auditable_type' => VendorCategoryRequest::class,
            'auditable_id' => $req->id,
            'action' => 'vendor_category_request_review_started',
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'under_review'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return $req->fresh();
    }

    public function approve(VendorCategoryRequest $req, User $reviewer, ?string $comments = null): VendorCategoryRequest
    {
        $this->assertOpen($req);

        return DB::transaction(function () use ($req, $reviewer, $comments) {
            $addIds = $req->items()->where('operation', 'add')->pluck('category_id')->all();
            $removeIds = $req->items()->where('operation', 'remove')->pluck('category_id')->all();

            if ($addIds) {
                $req->vendor->categories()->syncWithoutDetaching($addIds);
            }
            if ($removeIds) {
                $req->vendor->categories()->detach($removeIds);
            }

            $req->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'reviewer_comments' => $comments,
            ]);

            AuditLog::create([
                'user_id' => $reviewer->id,
                'vendor_id' => $req->vendor_id,
                'auditable_type' => VendorCategoryRequest::class,
                'auditable_id' => $req->id,
                'action' => 'vendor_category_request_approved',
                'old_values' => null,
                'new_values' => ['added' => $addIds, 'removed' => $removeIds, 'comments' => $comments],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            $req->vendor->notify(new VendorCategoryRequestApproved($req));

            return $req->fresh(['items.category', 'evidence', 'reviewer']);
        });
    }

    public function reject(VendorCategoryRequest $req, User $reviewer, string $comments): VendorCategoryRequest
    {
        $this->assertOpen($req);

        if (trim($comments) === '') {
            throw ValidationException::withMessages([
                'comments' => __('validation.vendor_category_request.reject_requires_comments'),
            ]);
        }

        $req->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reviewer_comments' => $comments,
        ]);

        AuditLog::create([
            'user_id' => $reviewer->id,
            'vendor_id' => $req->vendor_id,
            'auditable_type' => VendorCategoryRequest::class,
            'auditable_id' => $req->id,
            'action' => 'vendor_category_request_rejected',
            'old_values' => null,
            'new_values' => ['comments' => $comments],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        $req->vendor->notify(new VendorCategoryRequestRejected($req));

        return $req->fresh();
    }

    /**
     * Vendor withdraws their own request. `$reason` is optional — vendor UI
     * may collect it for analytics but doesn't require it.
     */
    public function withdraw(VendorCategoryRequest $req, Vendor $vendor, ?string $reason = null): VendorCategoryRequest
    {
        abort_unless($req->vendor_id === $vendor->id, 403);
        $this->assertOpen($req);

        $req->update([
            'status' => 'withdrawn',
            'withdraw_reason' => $reason,
        ]);

        AuditLog::create([
            'user_id' => null,
            'vendor_id' => $vendor->id,
            'auditable_type' => VendorCategoryRequest::class,
            'auditable_id' => $req->id,
            'action' => 'vendor_category_request_withdrawn',
            'old_values' => null,
            'new_values' => ['reason' => $reason],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        return $req->fresh();
    }

    /**
     * @throws ValidationException when the request has already terminated
     */
    private function assertOpen(VendorCategoryRequest $req): void
    {
        if (! in_array($req->status, ['pending', 'under_review'], true)) {
            throw ValidationException::withMessages([
                'status' => __('validation.vendor_category_request.invalid_state'),
            ]);
        }
    }
}
