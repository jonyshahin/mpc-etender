<?php

namespace App\Services;

use App\Enums\TenderStatus;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Handles tender lifecycle: creation, publishing, cancellation, and deadline management.
 */
class TenderService
{
    /**
     * Create a new tender in draft status.
     */
    public function create(array $data, User $creator): Tender
    {
        return DB::transaction(function () use ($data, $creator) {
            $categoryIds = $data['category_ids'] ?? [];
            unset($data['category_ids']);

            $data['created_by'] = $creator->id;
            $data['status'] = TenderStatus::Draft;
            $data['reference_number'] = $this->generateReferenceNumber($data['project_id']);

            $tender = Tender::create($data);

            if ($categoryIds) {
                $tender->categories()->attach($categoryIds);
            }

            return $tender;
        });
    }

    /**
     * Update a draft tender.
     */
    public function update(Tender $tender, array $data): Tender
    {
        return DB::transaction(function () use ($tender, $data) {
            $categoryIds = $data['category_ids'] ?? null;
            unset($data['category_ids']);

            $tender->update($data);

            if ($categoryIds !== null) {
                $tender->categories()->sync($categoryIds);
            }

            return $tender->fresh();
        });
    }

    /**
     * Publish a draft tender, making it visible to qualified vendors.
     */
    public function publish(Tender $tender): void
    {
        $tender->update([
            'status' => TenderStatus::Published,
            'publish_date' => now(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => Tender::class,
            'auditable_id' => $tender->id,
            'action' => 'published',
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'published'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Cancel a tender with reason.
     */
    public function cancel(Tender $tender, string $reason): void
    {
        $oldStatus = $tender->status->value;

        $tender->update([
            'status' => TenderStatus::Cancelled,
            'cancelled_reason' => $reason,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => Tender::class,
            'auditable_id' => $tender->id,
            'action' => 'updated',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'cancelled', 'cancelled_reason' => $reason],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Close submission at deadline. Called by scheduler.
     */
    public function closeSubmission(Tender $tender): void
    {
        $tender->update([
            'status' => TenderStatus::SubmissionClosed,
        ]);
    }

    /**
     * Get active tenders for a project.
     */
    public function getActiveForProject(Project $project): Collection
    {
        return $project->tenders()
            ->whereNotIn('status', [TenderStatus::Cancelled, TenderStatus::Completed])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Generate a unique reference number for a tender.
     */
    private function generateReferenceNumber(string $projectId): string
    {
        $project = Project::findOrFail($projectId);
        $count = Tender::where('project_id', $projectId)->count() + 1;

        return $project->code.'-T'.str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
