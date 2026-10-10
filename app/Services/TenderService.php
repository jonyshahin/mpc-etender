<?php

namespace App\Services;

use App\Enums\TenderStatus;
use App\Exceptions\TenderPublishException;
use App\Models\AuditLog;
use App\Models\BoqItem;
use App\Models\BoqSection;
use App\Models\EvaluationCriteriaTemplate;
use App\Models\EvaluationCriterion;
use App\Models\Project;
use App\Models\Tender;
use App\Models\TenderEngineProfile;
use App\Models\User;
use App\Services\DecisionEngine\DecisionEngineException;
use App\Services\DecisionEngine\EngineProfileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Handles tender lifecycle: creation, publishing, cancellation, and deadline management.
 */
class TenderService
{
    public function __construct(
        private FileUploadService $fileUploadService,
        private EngineProfileService $engineProfiles,
    ) {}

    /**
     * Create a new tender, optionally opening it for bidding straight away.
     *
     * With $publish, the publish prerequisites (BOQ, criteria, documents,
     * deadline, scoring criteria) are checked inside the create transaction, so
     * a tender that is not ready rolls back as it always has and the wizard
     * keeps the officer's input. Locking the engine profile happens only after
     * the draft has committed: it is a network call, and an engine that cannot
     * be reached must not throw away the officer's uploads with it
     * (INTEGRATION-DESIGN §3). That failure leaves a saved draft, carried on
     * the exception, for the officer to retry from.
     *
     * @throws TenderPublishException
     */
    public function create(array $data, User $creator, array $documents = [], bool $publish = false): Tender
    {
        $engineProfile = $data['engine_profile'] ?? null;
        unset($data['engine_profile']);

        $tender = DB::transaction(function () use ($data, $creator, $documents, $publish, $engineProfile) {
            $categoryIds = $data['category_ids'] ?? [];
            $boqSections = $data['boq_sections'] ?? [];
            $criteria = $data['evaluation_criteria'] ?? [];
            unset($data['category_ids'], $data['boq_sections'], $data['evaluation_criteria'], $data['documents'], $data['publish']);

            $data['created_by'] = $creator->id;
            $data['status'] = TenderStatus::Draft;
            $data['reference_number'] = $this->generateReferenceNumber($data['project_id']);

            $tender = Tender::create($data);

            if ($categoryIds) {
                $tender->categories()->attach(array_values(array_unique($categoryIds)));
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

            $this->createEngineProfile($tender, $engineProfile);

            if ($publish) {
                $this->assertPublishPrerequisites($tender);
            }

            return $tender;
        });

        if ($publish) {
            try {
                $this->openForBidding($tender);
            } catch (TenderPublishException $e) {
                throw $e->withSavedDraft($tender);
            }
        }

        return $tender->refresh();
    }

    /**
     * A new tender's copy of the scoring template, with whatever the wizard
     * already chose on top. Anything the wizard did not send keeps the
     * template's value.
     */
    private function createEngineProfile(Tender $tender, ?array $chosen): TenderEngineProfile
    {
        $profile = TenderEngineProfile::fromTemplate($tender, EvaluationCriteriaTemplate::standard());

        if ($chosen) {
            $profile->fill(array_intersect_key($chosen, array_flip([
                'weight_price', 'weight_technical', 'weight_timeline', 'weight_references',
                'weight_payment_flexibility', 'gate_bank_guarantee', 'gate_mandatory_documents',
                'margin_below', 'contract_type', 'contract_type_label', 'timeline_formula',
            ])));
        }

        $profile->save();

        return $profile;
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
     *
     * @throws TenderPublishException when prerequisites are not met
     */
    public function publish(Tender $tender): void
    {
        $this->assertPublishPrerequisites($tender);

        $this->openForBidding($tender);
    }

    /**
     * Lock the scoring profile in the engine, then open the tender.
     *
     * If the lock call fails, the tender does not open (INTEGRATION-DESIGN §3).
     * Not "open and lock later", not a background retry: a tender accepting
     * bids against weights that can still change is exactly the hole the lock
     * closes. The engine call runs before the status changes and outside any
     * transaction; only once the engine has confirmed the lock does one
     * transaction record it and open the tender. Publish is the retry, and it
     * is safe — see EngineProfileService::lock().
     *
     * @throws TenderPublishException with the engine's reason
     */
    private function openForBidding(Tender $tender): void
    {
        try {
            $lock = $this->engineProfiles->lock($tender, $tender->engineProfile()->firstOrFail());
        } catch (DecisionEngineException|RuntimeException $e) {
            $this->recordLockFailure($tender, $e);

            throw new TenderPublishException(__('engine.publish.lock_failed', ['reason' => $e->getMessage()]));
        }

        DB::transaction(function () use ($tender, $lock) {
            // Everything below re-reads under a row lock. The engine call above
            // took seconds, and the tender was an editable draft throughout:
            // its criteria, BOQ and documents may all have changed since the
            // checks that preceded the call. EngineProfileController takes the
            // same lock, so nothing can change between here and the commit.
            $current = Tender::whereKey($tender->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== TenderStatus::Draft) {
                // Two officers pressing Publish on the same criteria both get
                // here — the engine lock is idempotent — and the first opened
                // it. Anything else (a cancellation, a publish of different
                // criteria) must not be reported back as this publish working.
                if ($current->engineProfile()->value('profile_key') === $lock['profile_key']) {
                    return;
                }

                throw new TenderPublishException(__('engine.publish.status_changed'));
            }

            $profile = $current->engineProfile()->lockForUpdate()->firstOrFail();

            // The engine locked what was read before the call. If the criteria
            // were saved again during it, the row now holds weights the engine
            // never locked — refuse rather than freeze the two out of step.
            // The retry locks a new profile built from the current criteria.
            $expected = ['key' => $lock['profile_key']]
                + $this->engineProfiles->definition($current, $profile, $lock['schema_version']);
            if (EngineProfileService::canonical($expected) !== EngineProfileService::canonical($lock['definition'])) {
                throw new TenderPublishException(__('engine.publish.criteria_changed'));
            }

            // The same for everything else publishing requires: a BOQ item or
            // the only document can be deleted while the engine call runs.
            $this->assertPublishPrerequisites($current);

            $profile->update([
                'schema_key' => $lock['schema_key'],
                'schema_version' => $lock['schema_version'],
                'profile_key' => $lock['profile_key'],
                'definition' => $lock['definition'],
                'locked_at' => Carbon::parse($lock['locked_at']),
                'locked_by' => auth()->id(),
            ]);

            $current->update([
                'status' => TenderStatus::Published,
                'publish_date' => now(),
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'auditable_type' => Tender::class,
                'auditable_id' => $tender->id,
                'action' => 'published',
                'old_values' => ['status' => 'draft'],
                'new_values' => [
                    'status' => 'published',
                    'engine_profile_key' => $lock['profile_key'],
                    'engine_profile_locked_at' => $lock['locked_at'],
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        });

        $tender->refresh();
    }

    /**
     * Record a refused or failed lock. The audit trail should show that an
     * officer tried to open the tender and why it stayed closed, not only the
     * eventual success.
     */
    private function recordLockFailure(Tender $tender, Throwable $e): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => Tender::class,
            'auditable_id' => $tender->id,
            'action' => 'engine_lock_failed',
            'old_values' => null,
            'new_values' => [
                'reason' => $e->getMessage(),
                'engine_unreachable' => $e instanceof DecisionEngineException && $e->unavailable,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Enforce the preconditions for publishing. Each failure throws a
     * TenderPublishException with a translation key the controller wraps in
     * messages.tender_publish_failed.
     *
     * Weight comparison strategy: weight_percentage is decimal(5,2), so we
     * multiply each value by 100 and sum as integers — 40.00 → 4000 — and
     * compare against 10000. This avoids floating-point drift entirely and
     * keeps equality a strict `===` instead of a tolerance window.
     */
    private function assertPublishPrerequisites(Tender $tender): void
    {
        $tender->loadMissing(['boqSections.items', 'evaluationCriteria']);

        // 1. At least one BOQ section that has at least one item.
        $hasBoqItems = $tender->boqSections->contains(fn (BoqSection $s) => $s->items->isNotEmpty());
        if (! $hasBoqItems) {
            throw new TenderPublishException(__('messages.publish_reason_no_boq'));
        }

        // 2. At least one evaluation criterion.
        $criteria = $tender->evaluationCriteria;
        if ($criteria->isEmpty()) {
            throw new TenderPublishException(__('messages.publish_reason_no_criteria'));
        }

        // 3a. Two-envelope coverage: both technical AND financial must have criteria.
        if ($tender->is_two_envelope) {
            $envelopes = $criteria->pluck('envelope')->map(fn ($e) => is_object($e) ? $e->value : $e)->unique();
            if (! $envelopes->contains('technical') || ! $envelopes->contains('financial')) {
                throw new TenderPublishException(__('messages.publish_reason_weights_imbalanced'));
            }
        }

        // 3b. Per-envelope weight sum === 100.00 (checked as integer hundredths).
        $grouped = $criteria->groupBy(fn (EvaluationCriterion $c) => is_object($c->envelope) ? $c->envelope->value : $c->envelope);
        foreach ($grouped as $envelopeCriteria) {
            $sumHundredths = $envelopeCriteria->sum(
                fn (EvaluationCriterion $c) => (int) round(((float) $c->weight_percentage) * 100)
            );
            if ($sumHundredths !== 10000) {
                throw new TenderPublishException(__('messages.publish_reason_weights_imbalanced'));
            }
        }

        // 4. Submission deadline must still be in the future.
        if ($tender->submission_deadline !== null && $tender->submission_deadline->isPast()) {
            throw new TenderPublishException(__('messages.publish_reason_deadline_past'));
        }

        // 5. At least one current document must be attached. Documents are
        // a first-class sub-resource (like BOQ) — vendors cannot bid on a
        // tender with no specs/drawings/contract terms attached. BUG-22 fix.
        if ($tender->documents()->where('is_current', true)->count() === 0) {
            throw new TenderPublishException(__('messages.publish_reason_no_documents'));
        }

        // 6. Engine scoring criteria complete. Drafts that predate engine
        // scoring got their copy of the template when it shipped; a contract
        // type is the one thing the template cannot supply.
        $profile = $tender->engineProfile()->first();
        if ($profile === null) {
            throw new TenderPublishException(__('engine.publish.no_profile'));
        }
        if ($reason = $profile->incompleteReason()) {
            throw new TenderPublishException($reason);
        }
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
