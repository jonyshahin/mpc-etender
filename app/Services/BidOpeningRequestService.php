<?php

namespace App\Services;

use App\Enums\BidOpeningRequestStatus;
use App\Enums\TenderStatus;
use App\Models\BidOpeningRequest;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dual authorisation for opening a tender's sealed bids.
 *
 * The control exists to be pointed at in a dispute, so it has to be real: two
 * different people each taking an action from their own session. Previously the
 * opener named an `authorizer_id` in their own POST and the bids opened at
 * once — one person could produce a complete, audited "dual" authorisation for
 * a colleague who never acted.
 */
class BidOpeningRequestService
{
    /**
     * How long a request stays confirmable.
     *
     * Opening is a scheduled ceremony with both parties present, so this is
     * generous for the real flow while keeping a forgotten request from being
     * confirmable the following week.
     */
    public const WINDOW_MINUTES = 30;

    public function __construct(
        private BidSealingService $sealingService,
    ) {}

    /**
     * The opener's half: raise a request naming who must countersign.
     *
     * @throws ValidationException
     */
    public function request(Tender $tender, User $opener, User $authorizer): BidOpeningRequest
    {
        $this->assertOpenable($tender);
        $this->assertDistinctAndEligible($tender, $opener, $authorizer);

        if ($this->actionableFor($tender) !== null) {
            throw ValidationException::withMessages([
                'authorizer_id' => __('An opening request is already awaiting confirmation for this tender.'),
            ]);
        }

        return BidOpeningRequest::create([
            'tender_id' => $tender->id,
            'requested_by' => $opener->id,
            'authorizer_id' => $authorizer->id,
            'status' => BidOpeningRequestStatus::Pending,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(self::WINDOW_MINUTES),
        ]);
    }

    /**
     * The authorizer's half. Only they can perform it.
     *
     * Everything is re-checked here rather than trusted from request time: the
     * tender may have moved on, and either party may have lost the permission
     * or been taken off the project in between.
     *
     * @throws ValidationException
     */
    public function confirm(BidOpeningRequest $request, User $confirmer): void
    {
        if ($confirmer->id !== $request->authorizer_id) {
            throw ValidationException::withMessages([
                'confirm' => __('Only the nominated authorizer can confirm this opening.'),
            ]);
        }

        // Belt and braces. The opener cannot nominate themselves, so this can
        // only fire if requested_by changed underneath us — but a control whose
        // whole purpose is "two different people" should say so out loud.
        if ($confirmer->id === $request->requested_by) {
            throw ValidationException::withMessages([
                'confirm' => __('Bid opening requires two different users.'),
            ]);
        }

        if (! $request->isActionable()) {
            throw ValidationException::withMessages([
                'confirm' => $request->hasExpired()
                    ? __('This opening request has expired. Ask for a new one.')
                    : __('This opening request is no longer awaiting confirmation.'),
            ]);
        }

        $tender = $request->tender;
        $opener = $request->requester;

        $this->assertOpenable($tender);
        $this->assertDistinctAndEligible($tender, $opener, $confirmer);

        DB::transaction(function () use ($request, $tender, $opener, $confirmer) {
            // Claim the request first. Two confirmations racing would otherwise
            // both pass isActionable() and open the bids twice.
            $claimed = BidOpeningRequest::whereKey($request->id)
                ->where('status', BidOpeningRequestStatus::Pending)
                ->update([
                    'status' => BidOpeningRequestStatus::Confirmed,
                    'confirmed_at' => now(),
                    'confirmed_by' => $confirmer->id,
                ]);

            if ($claimed === 0) {
                throw ValidationException::withMessages([
                    'confirm' => __('This opening request is no longer awaiting confirmation.'),
                ]);
            }

            $this->sealingService->openBids($tender, $opener, $confirmer);

            $tender->update(['status' => TenderStatus::UnderEvaluation]);
        });
    }

    /** Either party may call the ceremony off. */
    public function cancel(BidOpeningRequest $request, User $user): void
    {
        if (! in_array($user->id, [$request->requested_by, $request->authorizer_id], true)) {
            throw ValidationException::withMessages([
                'cancel' => __('Only the requester or the nominated authorizer can cancel this.'),
            ]);
        }

        if ($request->status !== BidOpeningRequestStatus::Pending) {
            return;
        }

        $request->update([
            'status' => BidOpeningRequestStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /** The live request for a tender, if one is still confirmable. */
    public function actionableFor(Tender $tender): ?BidOpeningRequest
    {
        return BidOpeningRequest::where('tender_id', $tender->id)
            ->actionable()
            ->with(['requester:id,name', 'authorizer:id,name'])
            ->latest('requested_at')
            ->first();
    }

    /**
     * The tender-side conditions, enforced server-side.
     *
     * summary() computed "date passed AND submissions closed" for the UI, but
     * only the date half was ever enforced on the POST.
     *
     * @throws ValidationException
     */
    private function assertOpenable(Tender $tender): void
    {
        if ($tender->status !== TenderStatus::SubmissionClosed) {
            throw ValidationException::withMessages([
                'tender' => __('Bids can only be opened once submissions have closed.'),
            ]);
        }

        if (! $this->sealingService->canOpen($tender)) {
            throw ValidationException::withMessages([
                'tender' => __('Bids cannot be opened before the opening date.'),
            ]);
        }
    }

    /**
     * Both parties must be genuinely entitled, and genuinely two people.
     *
     * @throws ValidationException
     */
    private function assertDistinctAndEligible(Tender $tender, User $opener, User $authorizer): void
    {
        if ($opener->id === $authorizer->id) {
            throw ValidationException::withMessages([
                'authorizer_id' => __('Bid opening requires two different users.'),
            ]);
        }

        foreach ([$opener, $authorizer] as $party) {
            if (! $party->hasPermission('bids.open') || ! $party->isAssignedToProject($tender->project_id)) {
                throw ValidationException::withMessages([
                    'authorizer_id' => __('Both parties must hold the bid-opening permission and be assigned to this project.'),
                ]);
            }
        }
    }
}
