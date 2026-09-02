<?php

namespace App\Http\Controllers\Vendor;

use App\Concerns\FiltersLists;
use App\Enums\BidDocType;
use App\Enums\BidStatus;
use App\Enums\EnvelopeType;
use App\Enums\TenderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\BidDocumentRequest;
use App\Http\Requests\Vendor\BidSubmissionRequest;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\Tender;
use App\Services\BidService;
use App\Services\FileUploadService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BidController extends Controller
{
    use FiltersLists;

    public function __construct(
        private BidService $bidService,
        private FileUploadService $fileUploadService,
    ) {}

    /**
     * Columns the bid list may be ordered by.
     *
     * A whitelist because orderBy() does not check the column: Laravel
     * validates the direction and hands the column straight to the grammar,
     * so `?sort=submission_ip` was a 500 waiting to happen. The five columns
     * the table renders as sortable are exactly these.
     */
    private const SORTABLE = [
        'bid_reference',
        'status',
        'total_amount',
        'submitted_at',
        'created_at',
    ];

    /**
     * The vendor's own bids.
     *
     * Hand-projected rather than passing the models: a Bid row carries
     * submission_ip, submission_user_agent and opened_by, none of which this
     * five-column list has ever displayed.
     */
    public function index(Request $request): Response
    {
        $vendor = $request->user('vendor');

        $search = $this->searchTerm($request);
        $status = $this->filterValue($request, 'status');

        // Everything bar the status filter, so the tab counts come off the
        // same scope the rows do.
        $base = fn () => Bid::query()
            ->where('bids.vendor_id', $vendor->id)
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('bid_reference', 'like', "%{$search}%")
                    ->orWhereHas('tender', fn ($t) => $t
                        ->where('title_en', 'like', "%{$search}%")
                        ->orWhere('title_ar', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%"));
            }));

        $sort = in_array($request->input('sort'), self::SORTABLE, true)
            ? $request->input('sort')
            : 'submitted_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $bids = $base()
            ->select(
                'bids.id',
                'bids.tender_id',
                'bids.bid_reference',
                'bids.status',
                'bids.total_amount',
                'bids.currency',
                'bids.submitted_at',
                'bids.created_at',
            )
            ->when($status, fn ($q) => $q->where('bids.status', $status))
            ->with(['tender:id,title_en,title_ar,reference_number,submission_deadline,opening_date,currency,status'])
            // A draft has no submitted_at, so sorting by it alone leaves every
            // draft in whatever order the engine felt like. created_at breaks
            // the tie so the order is stable between requests.
            ->orderBy($sort, $direction)
            ->orderByDesc('bids.created_at')
            ->paginate(15)
            ->withQueryString();

        // Bid hides total_amount by default so no screen leaks a price by
        // omission. A vendor looking at their own bids is the clearest case
        // for revealing it: the query is already scoped to their vendor_id,
        // and it is their own number.
        $bids->through(fn (Bid $bid) => $bid->makeVisible('total_amount'));

        return Inertia::render('vendor/Bids/Index', [
            'bids' => $bids,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusCounts' => $this->bidStatusCounts($base()),
            'summary' => $this->bidSummary($base()),
            'statusOptions' => BidStatus::options(),
        ]);
    }

    /**
     * How many bids sit at each status, every status present.
     *
     * @return array<string, int>
     */
    private function bidStatusCounts(Builder $query): array
    {
        $counts = $query->select('bids.status', DB::raw('COUNT(*) as count'))
            ->groupBy('bids.status')
            ->pluck('count', 'status');

        return collect(BidStatus::cases())
            ->mapWithKeys(fn (BidStatus $case) => [$case->value => (int) ($counts[$case->value] ?? 0)])
            ->all();
    }

    /**
     * Headline figures, on the same scope as the rows beneath them.
     *
     * @return array<string, int>
     */
    private function bidSummary(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'drafts' => (clone $query)->where('bids.status', BidStatus::Draft)->count(),
            'submitted' => (clone $query)->where('bids.status', BidStatus::Submitted)->count(),
            // A draft on a tender that is still open is the only thing on this
            // page the vendor can still lose by not acting on.
            'awaiting_deadline' => (clone $query)
                ->where('bids.status', BidStatus::Draft)
                ->whereHas('tender', fn ($t) => $t
                    ->where('status', TenderStatus::Published)
                    ->where('submission_deadline', '>', now()))
                ->count(),
        ];
    }

    /**
     * Entry point from a tender's "Start Bid" button. Reuses an existing
     * bid for this (tender, vendor) regardless of status — submission is
     * final, the DB unique constraint enforces it, and the universal
     * Show page handles draft / submitted / withdrawn / etc. display
     * (BUG-19). Always lands the vendor on vendor.bids.show.
     */
    public function create(Request $request, Tender $tender): RedirectResponse
    {
        $vendor = $request->user('vendor');

        $bid = $tender->bids()
            ->where('vendor_id', $vendor->id)
            ->first();

        if ($bid === null) {
            // Policy gate runs only on the new-draft path. The existing-bid
            // path above always reaches the Show page so a vendor whose
            // status later changes (e.g. suspended) can still revisit
            // their prior bid.
            abort_unless(Gate::forUser($vendor)->check('create', [Bid::class, $tender]), 403);

            try {
                $bid = $this->bidService->createDraft($tender, $vendor);
            } catch (QueryException $e) {
                // Defensive backstop: if the application-level guards in
                // BidService::validateSubmissionAllowed ever miss the
                // (tender_id, vendor_id) unique constraint, surface a
                // generic translated toast — never leak the SQL message
                // to the vendor. report() so ops still gets the trace.
                report($e);
                Inertia::flash('toast', ['type' => 'error', 'message' => __('messages.bid.create_failed_unexpected')]);

                return redirect()->route('vendor.tenders.show', $tender);
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
            'tender:id,title_en,title_ar,reference_number,currency,status,submission_deadline,opening_date,is_two_envelope',
            'tender.boqSections:id,tender_id,title,title_ar,sort_order',
            'tender.boqSections.items:id,section_id,item_code,description_en,description_ar,unit,quantity,sort_order',
            'boqPrices:id,bid_id,boq_item_id,unit_price,total_price,remarks',
            'documents',
        ]);

        $tender = $bid->tender;
        $canEdit = $bid->status === BidStatus::Draft && $tender->is_open_for_submission;
        $canWithdraw = $bid->status === BidStatus::Submitted && $tender->is_open_for_submission;

        // Group documents by envelope for the React side. The bid row carries
        // 'single' as a legacy value (see BidService::createDraft); the actual
        // envelope split lives on bid_documents.envelope_type.
        $groupedDocs = $bid->documents
            ->groupBy(fn ($d) => $d->envelope_type)
            ->map(fn ($docs) => $docs->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'original_filename' => $d->original_filename,
                'file_size' => $d->file_size,
                'mime_type' => $d->mime_type,
                'doc_type' => $d->doc_type instanceof BidDocType ? $d->doc_type->value : $d->doc_type,
                'envelope_type' => $d->envelope_type,
                'uploaded_at' => $d->uploaded_at,
                'download_url' => route('vendor.bids.documents.download', [$bid, $d]),
            ]))
            ->toArray();

        return Inertia::render('vendor/Bids/Show', [
            'bid' => [
                'id' => $bid->id,
                'bid_reference' => $bid->bid_reference,
                'status' => $bid->status->value,
                'status_label_key' => $bid->status->labelKey(),
                // Reading the attribute never consults $hidden — that governs
                // serialisation — so this payload is explicit about including
                // the price. It is the vendor's own, on a page behind
                // BidPolicy::view.
                'total_amount' => $bid->total_amount,
                'currency' => $bid->currency,
                'technical_notes' => $bid->technical_notes,
                'submitted_at' => $bid->submitted_at,
                'is_sealed' => (bool) $bid->is_sealed,
                'withdrawal_reason' => $bid->withdrawal_reason,
                'is_withdrawn' => $bid->status === BidStatus::Withdrawn,
                'is_rejected' => in_array($bid->status, [BidStatus::Rejected, BidStatus::Disqualified], true),
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
                'is_two_envelope' => (bool) $tender->is_two_envelope,
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
            'documents' => [
                'single' => $groupedDocs['single'] ?? [],
                'technical' => $groupedDocs['technical'] ?? [],
                'financial' => $groupedDocs['financial'] ?? [],
            ],
            'canEdit' => $canEdit,
            'canSubmit' => $canEdit,
            'canManageDocuments' => $canEdit,
            'canWithdraw' => $canWithdraw,
            // The page kept three hardcoded copies of these lists, one per
            // envelope picker, which BidDocumentRequest's validation had no way
            // to stay in step with. One source now feeds both.
            'docTypes' => [
                'single' => BidDocType::optionsFor(EnvelopeType::Single),
                'technical' => BidDocType::optionsFor(EnvelopeType::Technical),
                'financial' => BidDocType::optionsFor(EnvelopeType::Financial),
            ],
        ]);
    }

    /**
     * Upload a document onto a draft bid. PDF only, size capped by POLICY-01
     * (App\Rules\PdfFile), validated by
     * BidDocumentRequest. Storage goes through FileUploadService per the
     * project upload mandate. Caller picks envelope via the form payload —
     * 'single' for single-envelope tenders, 'technical' or 'financial' for
     * two-envelope (BUG-18 sub-phase A).
     */
    public function storeDocument(BidDocumentRequest $request, Bid $bid): RedirectResponse
    {
        $vendor = $request->user('vendor');
        abort_unless(Gate::forUser($vendor)->check('manageDocuments', $bid), 403);

        $file = $request->file('file');
        $validated = $request->validated();

        $path = $this->fileUploadService->upload(
            $file,
            "vendors/{$vendor->id}/bids/{$bid->id}/documents",
            'pdf',
        );

        $bid->documents()->create([
            'title' => $validated['title'],
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'doc_type' => $validated['doc_type'],
            'envelope_type' => $validated['envelope_type'],
            'uploaded_by_vendor_id' => $vendor->id,
            'uploaded_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.bid.document_uploaded')]);

        return back();
    }

    /**
     * Remove a document from a draft bid. Owner-only, draft-only.
     */
    public function destroyDocument(Request $request, Bid $bid, BidDocument $document): RedirectResponse
    {
        $vendor = $request->user('vendor');
        abort_unless(Gate::forUser($vendor)->check('manageDocuments', $bid), 403);
        abort_unless($document->bid_id === $bid->id, 404);

        $this->fileUploadService->delete($document->file_path);
        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.bid.document_deleted')]);

        return back();
    }

    /**
     * Stream a bid document to the requesting vendor. Goes through the
     * BidPolicy::viewDocument gate so we never expose raw S3 URLs to the
     * React client. Future BUG-20 work extends the policy with phase-aware
     * gating for evaluators.
     */
    public function downloadDocument(Request $request, Bid $bid, BidDocument $document): StreamedResponse
    {
        $vendor = $request->user('vendor');
        // Gate dispatches by the FIRST argument's class — pass $bid (registered to
        // BidPolicy) and let the policy method receive $document as the extra arg.
        // Avoids needing a separate BidDocumentPolicy for the one-method override.
        abort_unless(Gate::forUser($vendor)->check('viewDocument', [$bid, $document]), 403);
        abort_unless($document->bid_id === $bid->id, 404);

        return Storage::disk('s3')->download(
            $document->file_path,
            $document->original_filename ?? $document->title,
        );
    }
}
