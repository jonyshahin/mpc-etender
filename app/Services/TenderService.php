<?php

namespace App\Services;

use App\Enums\TenderStatus;
use App\Models\AuditLog;
use App\Models\BoqItem;
use App\Models\BoqSection;
use App\Models\EvaluationCriterion;
use App\Models\Project;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Handles tender lifecycle: creation, publishing, cancellation, and deadline management.
 */
class TenderService
{
    public function __construct(
        private FileUploadService $fileUploadService,
    ) {}

    /**
     * Create a new tender. When $publish is true, the draft is created and
     * then published within the same DB transaction — if publish() fails,
     * the whole create rolls back, leaving no orphan draft.
     */
    public function create(array $data, User $creator, array $documents = [], bool $publish = false): Tender
    {
        return DB::transaction(function () use ($data, $creator, $documents, $publish) {
            $categoryIds = $data['category_ids'] ?? [];
            $boqSections = $data['boq_sections'] ?? [];
            $criteria = $data['evaluation_criteria'] ?? [];
            unset($data['category_ids'], $data['boq_sections'], $data['evaluation_criteria'], $data['documents'], $data['publish']);

            $data['created_by'] = $creator->id;
            $data['status'] = TenderStatus::Draft;
            $data['reference_number'] = $this->generateReferenceNumber($data['project_id']);

            $tender = Tender::create($data);

            if ($categoryIds) {
                $tender->categories()->attach($categoryIds);
            }

            foreach ($boqSections as $sectionIndex => $sectionData) {
                $section = BoqSection::create([
                    'tender_id' => $tender->id,
                    'title' => $sectionData['title_en'],
                    'title_ar' => $sectionData['title_ar'] ?? null,
                    'sort_order' => $sectionData['sort_order'] ?? $sectionIndex,
                ]);

                foreach ($sectionData['items'] ?? [] as $itemIndex => $itemData) {
                    BoqItem::create([
                        'section_id' => $section->id,
                        'item_code' => $itemData['item_code'],
                        'description_en' => $itemData['description_en'],
                        'unit' => $itemData['unit'],
                        'quantity' => $itemData['quantity'],
                        'sort_order' => $itemData['sort_order'] ?? $itemIndex,
                    ]);
                }
            }

            foreach ($criteria as $criterionIndex => $criterionData) {
                EvaluationCriterion::create([
                    'tender_id' => $tender->id,
                    'name_en' => $criterionData['name_en'],
                    'envelope' => $criterionData['envelope'],
                    'weight_percentage' => $criterionData['weight_percentage'],
                    'max_score' => $criterionData['max_score'],
                    'sort_order' => $criterionData['sort_order'] ?? $criterionIndex,
                ]);
            }

            foreach ($documents as $docData) {
                /** @var UploadedFile $file */
                $file = $docData['file'];
                $path = $this->fileUploadService->upload($file, "tenders/{$tender->id}/documents");

                $tender->documents()->create([
                    'uploaded_by' => $creator->id,
                    'title' => $docData['title'],
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'doc_type' => $docData['doc_type'],
                    'version' => 1,
                    'is_current' => true,
                ]);
            }

            if ($publish) {
                $this->publish($tender);
                $tender->refresh();
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
