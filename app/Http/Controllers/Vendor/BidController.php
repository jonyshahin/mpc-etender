<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\BidSubmissionRequest;
use App\Models\Bid;
use App\Models\Tender;
use App\Services\BidService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Show bid form. Creates a draft bid if one does not already exist,
     * then loads BOQ sections/items for pricing.
     */
    public function create(Request $request, Tender $tender): RedirectResponse|Response
    {
        $vendor = $request->user('vendor');

        $bid = $tender->bids()
            ->where('vendor_id', $vendor->id)
            ->whereNot('status', 'withdrawn')
            ->first();

        if ($bid === null) {
            try {
                $bid = $this->bidService->createDraft($tender, $vendor);
            } catch (\RuntimeException $e) {
                Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

                return redirect()->route('vendor.tenders.show', $tender);
            }
        }

        $tender->load(['boqSections.items', 'categories:id,name_en,name_ar', 'project:id,name']);

        return Inertia::render('vendor/Tenders/Bid/Create', [
            'tender' => $tender,
            'bid' => $bid,
            'boqSections' => $tender->boqSections,
        ]);
    }

    /**
     * Create a draft bid using BidService.
     */
    public function store(Request $request, Tender $tender): RedirectResponse
    {
        $vendor = $request->user('vendor');

        try {
            $bid = $this->bidService->createDraft($tender, $vendor);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft bid created successfully.')]);

            return redirect()->route('vendor.bids.show', $bid);
        } catch (\RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }
    }

    /**
     * Update BOQ pricing on a draft bid.
     */
    public function update(Request $request, Bid $bid): RedirectResponse
    {
        $vendor = $request->user('vendor');
        $this->ensureOwnership($bid, $vendor->id);

        $validated = $request->validate([
            'boq_prices' => ['required', 'array', 'min:1'],
            'boq_prices.*.boq_item_id' => ['required', 'uuid', 'exists:boq_items,id'],
            'boq_prices.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'boq_prices.*.total_price' => ['required', 'numeric', 'gte:0'],
            'boq_prices.*.remarks' => ['nullable', 'string'],
        ]);

        $this->bidService->updatePricing($bid, $validated['boq_prices']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bid pricing updated successfully.')]);

        return back();
    }

    /**
     * Submit (seal) a bid using BidService.
     */
    public function submit(BidSubmissionRequest $request, Bid $bid): RedirectResponse
    {
        $vendor = $request->user('vendor');
        $this->ensureOwnership($bid, $vendor->id);

        try {
            // Update pricing first if provided
            if ($request->has('boq_prices')) {
                $this->bidService->updatePricing($bid, $request->validated()['boq_prices']);
            }

            $this->bidService->submit($bid);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Bid submitted and sealed successfully.')]);

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
        $this->ensureOwnership($bid, $vendor->id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->bidService->withdraw($bid, $validated['reason']);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Bid withdrawn successfully.')]);

            return redirect()->route('vendor.bids.index');
        } catch (\RuntimeException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }
    }

    /**
     * View submitted bid details.
     */
    public function show(Request $request, Bid $bid): Response
    {
        $vendor = $request->user('vendor');
        $this->ensureOwnership($bid, $vendor->id);

        $bid->load([
            'tender:id,title_en,title_ar,reference_number,submission_deadline,opening_date,status',
            'boqPrices.boqItem',
            'documents',
        ]);

        return Inertia::render('vendor/Bids/Show', [
            'bid' => $bid,
        ]);
    }

    /**
     * Ensure the authenticated vendor owns the bid.
     *
     * @throws HttpException
     */
    private function ensureOwnership(Bid $bid, string $vendorId): void
    {
        if ($bid->vendor_id !== $vendorId) {
            abort(403, __('You do not have permission to access this bid.'));
        }
    }
}
