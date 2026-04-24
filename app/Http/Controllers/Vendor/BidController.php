<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\BidStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\BidSubmissionRequest;
use App\Models\Bid;
use App\Models\Tender;
use App\Services\BidService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BidController extends Controller
{
    public function __construct(
        private BidService $bidService,
    ) {}

    /**
     * Show vendor's bid list.
     */
    public function index(Request $request): Response
    {
        $vendor = $request->user('vendor');

        $bids = Bid::where('vendor_id', $vendor->id)
            ->with(['tender:id,title_en,title_ar,reference_number,submission_deadline,status'])
            ->latest()
            ->paginate(15);

        return Inertia::render('vendor/Bids/Index', [
            'bids' => $bids,
        ]);
    }

    /**
     * Entry point from a tender's "Start Bid" button. Reuses an existing
     * non-withdrawn draft if present, otherwise creates one. Always lands
     * the vendor on vendor.bids.show — the universal bid page handles the
     * editable / read-only / withdrawn states from there.
     */
    public function create(Request $request, Tender $tender): RedirectResponse
    {
        $vendor = $request->user('vendor');

        $bid = $tender->bids()
            ->where('vendor_id', $vendor->id)
            ->whereNot('status', BidStatus::Withdrawn->value)
            ->first();

        if ($bid === null) {
            // Policy gate runs only on the new-draft path so an existing draft
            // remains reachable even if the vendor's status later changes
            // (e.g. suspended after creating a draft they want to revisit).
            // BidService::validateSubmissionAllowed inside createDraft still
            // produces the per-failure translated toast — the policy is a
            // defensive backup, not the user-facing message channel.
            abort_unless(Gate::forUser($vendor)->check('create', [Bid::class, $tender]), 403);

            try {
                $bid = $this->bidService->createDraft($tender, $vendor);
            } catch (\RuntimeException $e) {
                Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

                return redirect()->route('vendor.tenders.show', $tender);
            }
        }

        return redirect()->route('vendor.bids.show', $bid);
    }

    /**
     * Update BOQ pricing on a draft bid.
     */
    public function update(Request $request, Bid $bid): RedirectResponse
    {
        $vendor = $request->user('vendor');
        abort_unless(Gate::forUser($vendor)->check('update', $bid), 403);

        $validated = $request->validate([
            'boq_prices' => ['required', 'array', 'min:1'],
            'boq_prices.*.boq_item_id' => ['required', 'uuid', 'exists:boq_items,id'],
            'boq_prices.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'boq_prices.*.total_price' => ['required', 'numeric', 'gte:0'],
            'boq_prices.*.remarks' => ['nullable', 'string'],
            'technical_notes' => ['nullable', 'string'],
        ]);

        $this->bidService->updatePricing($bid, $validated['boq_prices']);

        if (array_key_exists('technical_notes', $validated)) {
            $bid->update(['technical_notes' => $validated['technical_notes']]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.bid.draft_saved')]);

        return back();
    }

    /**
     * Submit (seal) a bid using BidService.
     */
    public function submit(BidSubmissionRequest $request, Bid $bid): RedirectResponse
    {
        $vendor = $request->user('vendor');
        abort_unless(Gate::forUser($vendor)->check('submit', $bid), 403);

        try {
            // Optional one-shot path: caller may include final prices in the submit
            // request to save+seal in a single round-trip. The Show page sends two
            // requests (Save Draft → Submit), so this branch is rarely taken.
            if ($request->has('boq_prices')) {
                $this->bidService->updatePricing($bid, $request->validated()['boq_prices']);
            }

            $this->bidService->submit($bid);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.bid.submitted')]);

            return redirect()->route('vendor.bids.show', $bid);
        } catch (\RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }
    }

    /**
     * Withdraw a submitted bid.
     */
    public function withdraw(Request $request, Bid $bid): RedirectResponse
    {
        $vendor = $request->user('vendor');
        abort_unless(Gate::forUser($vendor)->check('withdraw', $bid), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->bidService->withdraw($bid, $validated['reason']);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.bid.withdrawn')]);

            return redirect()->route('vendor.bids.index');
        } catch (\RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }
    }

    /**
     * Universal bid page — renders for every status. The React side switches
     * between editable / read-only / withdrawn views based on canEdit /
     * canSubmit / canWithdraw flags computed here. Payload is hand-projected
     * to keep encrypted_pricing_data and other server-only fields off the wire.
     */
    public function show(Request $request, Bid $bid): Response
    {
        $vendor = $request->user('vendor');
        abort_unless(Gate::forUser($vendor)->check('view', $bid), 403);

        $bid->load([
            'tender:id,title_en,title_ar,reference_number,currency,status,submission_deadline,opening_date',
            'tender.boqSections:id,tender_id,title,title_ar,sort_order',
            'tender.boqSections.items:id,section_id,item_code,description_en,description_ar,unit,quantity,sort_order',
            'boqPrices:id,bid_id,boq_item_id,unit_price,total_price,remarks',
        ]);

        $tender = $bid->tender;
        $canEdit = $bid->status === BidStatus::Draft && $tender->is_open_for_submission;
        $canWithdraw = $bid->status === BidStatus::Submitted && $tender->is_open_for_submission;

        return Inertia::render('vendor/Bids/Show', [
            'bid' => [
                'id' => $bid->id,
                'bid_reference' => $bid->bid_reference,
                'status' => $bid->status->value,
                'total_amount' => $bid->total_amount,
                'currency' => $bid->currency,
                'technical_notes' => $bid->technical_notes,
                'submitted_at' => $bid->submitted_at,
                'is_sealed' => $bid->is_sealed,
                'withdrawal_reason' => $bid->withdrawal_reason,
            ],
            'tender' => [
                'id' => $tender->id,
                'reference_number' => $tender->reference_number,
                'title_en' => $tender->title_en,
                'title_ar' => $tender->title_ar,
                'currency' => $tender->currency,
                'status' => $tender->status->value,
                'submission_deadline' => $tender->submission_deadline,
                'opening_date' => $tender->opening_date,
                'boq_sections' => $tender->boqSections
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn ($s) => [
                        'id' => $s->id,
                        'title' => $s->title,
                        'title_ar' => $s->title_ar,
                        'items' => $s->items
                            ->sortBy('sort_order')
                            ->values()
                            ->map(fn ($i) => [
                                'id' => $i->id,
                                'item_code' => $i->item_code,
                                'description_en' => $i->description_en,
                                'description_ar' => $i->description_ar,
                                'unit' => $i->unit,
                                'quantity' => $i->quantity,
                            ]),
                    ]),
            ],
            'boqPrices' => $bid->boqPrices->mapWithKeys(fn ($p) => [
                $p->boq_item_id => [
                    'unit_price' => $p->unit_price,
                    'total_price' => $p->total_price,
                ],
            ]),
            'canEdit' => $canEdit,
            'canSubmit' => $canEdit,
            'canWithdraw' => $canWithdraw,
        ]);
    }
}
