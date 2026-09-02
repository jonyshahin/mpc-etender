<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\TenderStatus;
use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Models\DocumentAccessLog;
use App\Models\Tender;
use App\Models\TenderDocument;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenderBrowseController extends Controller
{
    /**
     * Columns the browse list may be ordered by.
     *
     * A whitelist because orderBy() does not check the column: Laravel
     * validates the direction and throws on anything but asc/desc, but hands
     * the column straight to the grammar. `?sort=notes_internal` would have
     * been a 500 — and on a column a vendor must never learn exists.
     */
    private const SORTABLE = [
        'submission_deadline',
        'publish_date',
        'reference_number',
        'title_en',
    ];

    /**
     * Published tenders in the vendor's own categories.
     */
    public function index(Request $request): Response
    {
        $vendor = $request->user('vendor');

        $search = trim((string) $request->input('search'));
        $window = $request->input('window');

        // Everything bar the window filter, so the tab counts come off the
        // same scope the rows do.
        $base = fn () => $this->visibleTenders($vendor)
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('title_en', 'like', "%{$search}%")
                    ->orWhere('title_ar', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%");
            }));

        $sort = in_array($request->input('sort'), self::SORTABLE, true)
            ? $request->input('sort')
            : 'submission_deadline';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $tenders = $base()
            // A hand-picked column list rather than the whole row: Tender hides
            // the employer's estimate and internal notes, but naming the
            // columns keeps anything added to the table later off the wire too.
            ->select(
                'tenders.id',
                'tenders.project_id',
                'tenders.reference_number',
                'tenders.title_en',
                'tenders.title_ar',
                'tenders.status',
                'tenders.currency',
                'tenders.publish_date',
                'tenders.submission_deadline',
                'tenders.opening_date',
                'tenders.is_two_envelope',
                'tenders.requires_site_visit',
                'tenders.site_visit_date',
            )
            ->when($window === 'closing_soon', fn ($q) => $q->whereBetween('submission_deadline', [now(), now()->addDays(7)]))
            ->when($window === 'open', fn ($q) => $q->where('submission_deadline', '>', now()))
            ->when($window === 'bid_started', fn ($q) => $q->whereHas('bids', fn ($b) => $b->where('vendor_id', $vendor->id)))
            ->with([
                'project:id,name,name_ar',
                'categories:id,name_en,name_ar',
                // The vendor's own bid on each row, so a card can say
                // "continue" rather than "start". Scoped to this vendor: rival
                // bids are not this page's business, and the withCount('bids')
                // this replaces shipped a rival headcount the UI never rendered.
                'bids' => fn ($q) => $q->where('vendor_id', $vendor->id)
                    ->select('id', 'tender_id', 'status'),
            ])
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        $tenders->through(function (Tender $tender) {
            $bid = $tender->bids->first();
            $tender->unsetRelation('bids');
            // is_editable rather than a raw status: the card decides between
            // "Continue Bid" and "View Bid", and comparing against the literal
            // 'draft' in React put a copy of the enum in the browser.
            $tender->setAttribute('my_bid', $bid ? [
                'id' => $bid->id,
                'status' => $bid->status->value,
                'is_editable' => $bid->status->isEditable(),
            ] : null);

            return $tender;
        });

        return Inertia::render('vendor/Tenders/Browse', [
            'tenders' => $tenders,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'window' => $window,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'summary' => $this->browseSummary($base(), $vendor),
        ]);
    }

    /**
     * Tender detail from the vendor's side.
     *
     * Hand-projected rather than passing the model: a Tender row carries the
     * employer's own estimate and internal notes, and both browse screens were
     * shipping them to bidders — the detail page went as far as rendering the
     * estimate under an "Estimated Value" heading.
     */
    public function show(Request $request, Tender $tender): Response
    {
        $vendor = $request->user('vendor');

        // 404 rather than 403: whether a tender exists at a given id is itself
        // something a vendor outside its categories should not learn.
        abort_unless($this->mayView($vendor, $tender), 404);

        $tender->load([
            'project:id,name,name_ar',
            'categories:id,name_en,name_ar',
            'boqSections' => fn ($q) => $q->with('items')->orderBy('sort_order'),
            // is_current only: a vendor shown v1 and v2 of the same spec with
            // nothing to tell them apart may bid against the superseded one.
            'documents' => fn ($q) => $q->where('is_current', true)->orderBy('title'),
            'addenda' => fn ($q) => $q->orderByDesc('addendum_number'),
            'clarifications' => fn ($q) => $q->where('is_published', true)->orderByDesc('published_at'),
        ]);

        DocumentAccessLog::create([
            'vendor_id' => $vendor->id,
            'document_type' => Tender::class,
            'document_id' => $tender->id,
            'action' => 'viewed',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'accessed_at' => now(),
        ]);

        // Bid uniqueness is per (tender_id, vendor_id) at the DB level. Treat
        // any prior bid — draft, submitted, withdrawn — as occupying the slot;
        // submission is final per product spec.
        $existingBid = $tender->bids()
            ->where('vendor_id', $vendor->id)
            ->first();

        $canBid = $vendor->prequalification_status === VendorStatus::Qualified
            && $vendor->is_active
            && $tender->is_open_for_submission
            && $existingBid === null;

        return Inertia::render('vendor/Tenders/Show', [
            'tender' => [
                'id' => $tender->id,
                'reference_number' => $tender->reference_number,
                'title_en' => $tender->title_en,
                'title_ar' => $tender->title_ar,
                'description_en' => $tender->description_en,
                'description_ar' => $tender->description_ar,
                'tender_type_label_key' => $tender->tender_type->labelKey(),
                'status' => $tender->status->value,
                'currency' => $tender->currency,
                'publish_date' => $tender->publish_date,
                'submission_deadline' => $tender->submission_deadline,
                'opening_date' => $tender->opening_date,
                'is_two_envelope' => (bool) $tender->is_two_envelope,
                'requires_site_visit' => (bool) $tender->requires_site_visit,
                'site_visit_date' => $tender->site_visit_date,
                'project' => $tender->project ? [
                    'id' => $tender->project->id,
                    'name' => $tender->project->name,
                    'name_ar' => $tender->project->name_ar,
                ] : null,
                'categories' => $tender->categories->map(fn ($c) => [
                    'id' => $c->id,
                    'name_en' => $c->name_en,
                    'name_ar' => $c->name_ar,
                ])->values(),
                'boq_sections' => $tender->boqSections->map(fn ($s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'title_ar' => $s->title_ar,
                    'items' => $s->items->sortBy('sort_order')->values()->map(fn ($i) => [
                        'id' => $i->id,
                        'item_code' => $i->item_code,
                        'description_en' => $i->description_en,
                        'description_ar' => $i->description_ar,
                        'unit' => $i->unit,
                        'quantity' => $i->quantity,
                    ]),
                ])->values(),
                // No file_path: the S3 key is not the vendor's business, and
                // the download goes through a gated route that logs the access.
                'documents' => $tender->documents->map(fn (TenderDocument $d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'doc_type_label_key' => $d->doc_type->labelKey(),
                    'file_size' => $d->file_size,
                    'version' => $d->version,
                    'download_url' => route('vendor.tenders.documents.download', [$tender, $d]),
                ])->values(),
                'addenda' => $tender->addenda->map(fn ($a) => [
                    'id' => $a->id,
                    'addendum_number' => $a->addendum_number,
                    'subject' => $a->subject,
                    'content_en' => $a->content_en,
                    'content_ar' => $a->content_ar,
                    'extends_deadline' => (bool) $a->extends_deadline,
                    'new_deadline' => $a->new_deadline,
                    'published_at' => $a->published_at,
                ])->values(),
                // No asked_by: a published clarification is public Q&A, but
                // which rival asked it is not part of the answer.
                'clarifications' => $tender->clarifications->map(fn ($c) => [
                    'id' => $c->id,
                    'question' => $c->question,
                    'answer' => $c->answer,
                    'asked_at' => $c->asked_at,
                    'answered_at' => $c->answered_at,
                ])->values(),
            ],
            'canBid' => $canBid,
            'canAskClarification' => $tender->is_open_for_submission,
            'existingBid' => $existingBid
                ? [
                    'id' => $existingBid->id,
                    'status' => $existingBid->status->value,
                    'is_editable' => $existingBid->status->isEditable(),
                ]
                : null,
        ]);
    }

    /**
     * Stream a tender document to the vendor.
     *
     * The Show page has always rendered a Download button pointing at this
     * URL; the route did not exist, so every one of them was a 404 and a
     * vendor could not obtain the documents they are required to bid against.
     */
    public function downloadDocument(Request $request, Tender $tender, TenderDocument $document): StreamedResponse
    {
        $vendor = $request->user('vendor');

        abort_unless($this->mayView($vendor, $tender), 404);
        abort_unless($document->tender_id === $tender->id, 404);

        DocumentAccessLog::create([
            'vendor_id' => $vendor->id,
            'document_type' => TenderDocument::class,
            'document_id' => $document->id,
            'action' => 'downloaded',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'accessed_at' => now(),
        ]);

        return Storage::disk('s3')->download($document->file_path, $document->title.'.pdf');
    }

    /**
     * Tenders this vendor is entitled to see at all.
     *
     * Published only, and only in categories the vendor holds — this was the
     * whole of the access control the detail page lacked, which let any
     * authenticated vendor open any tender by id, draft ones included.
     */
    private function visibleTenders(Vendor $vendor): Builder
    {
        $categoryIds = $vendor->categories()->pluck('categories.id');

        return Tender::query()
            ->where('tenders.status', TenderStatus::Published)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds));
    }

    /**
     * Whether this vendor may look at this tender.
     *
     * A vendor who already bid keeps access even once the tender leaves
     * Published or drops out of their categories: they need to revisit what
     * they bid on, and the deadline they bid against. Entry is still governed
     * by visibleTenders() above.
     */
    private function mayView(Vendor $vendor, Tender $tender): bool
    {
        if ($tender->bids()->where('vendor_id', $vendor->id)->exists()) {
            return true;
        }

        return $this->visibleTenders($vendor)->whereKey($tender->getKey())->exists();
    }

    /**
     * Headline figures, on the same scope as the rows beneath them.
     *
     * @return array<string, int>
     */
    private function browseSummary(Builder $query, Vendor $vendor): array
    {
        return [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->where('submission_deadline', '>', now())->count(),
            // Worth its own tile: a deadline is something a bid can be
            // rejected for missing.
            'closing_soon' => (clone $query)
                ->whereBetween('submission_deadline', [now(), now()->addDays(7)])
                ->count(),
            'bid_started' => (clone $query)
                ->whereHas('bids', fn ($b) => $b->where('vendor_id', $vendor->id))
                ->count(),
        ];
    }
}
