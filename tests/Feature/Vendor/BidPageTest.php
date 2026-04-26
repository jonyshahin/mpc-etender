<?php

use App\Enums\BidDocType;
use App\Enums\BidStatus;
use App\Enums\EnvelopeType;
use App\Models\Bid;
use App\Models\BidBoqPrice;
use App\Models\BidDocument;
use App\Models\Tender;
use App\Models\Vendor;
use App\Services\BidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Build a published tender with one BOQ section+items and one category,
 * plus a qualified vendor matched to that category. Returns [vendor, tender].
 */
function bidFixture(): array
{
    // Pin is_two_envelope=false — TenderFactory's default uses fake()->boolean(30)
    // which would randomly produce two-envelope tenders and trip the BUG-18 guard
    // in tests that don't exercise that path.
    $tender = Tender::factory()
        ->published()
        ->withBoq(1, 2)
        ->withCategories(1)
        ->create(['is_two_envelope' => false]);
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $vendor->categories()->attach($tender->categories()->first()->id);

    return [$vendor, $tender];
}

function draftBid(Vendor $vendor, Tender $tender): Bid
{
    return Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'status' => BidStatus::Draft,
        'is_sealed' => false,
        'total_amount' => null,
        'encrypted_pricing_data' => null,
        'submitted_at' => null,
    ]);
}

test('show page for a draft renders canEdit with full tender BOQ', function () {
    [$vendor, $tender] = bidFixture();
    $bid = draftBid($vendor, $tender);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.bids.show', $bid));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('vendor/Bids/Show')
        ->where('canEdit', true)
        ->where('canSubmit', true)
        ->where('canWithdraw', false)
        ->has('tender.boq_sections', 1)
        ->has('tender.boq_sections.0.items', 2)
    );
});

test('show page for a submitted bid is read-only with withdraw enabled', function () {
    [$vendor, $tender] = bidFixture();
    $bid = Bid::factory()->submitted()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.bids.show', $bid));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('canEdit', false)
        ->where('canSubmit', false)
        ->where('canWithdraw', true)
    );
});

test('show response never leaks encrypted_pricing_data', function () {
    [$vendor, $tender] = bidFixture();
    // Seed encrypted_pricing_data on the row so we would notice a leak.
    $bid = Bid::factory()->submitted()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'encrypted_pricing_data' => 'pretend-cipher-text',
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.bids.show', $bid));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->missing('bid.encrypted_pricing_data')
    );
});

test('PUT /vendor/bids/{id} persists BOQ prices on a draft', function () {
    [$vendor, $tender] = bidFixture();
    $bid = draftBid($vendor, $tender);
    $item = $tender->boqSections->first()->items->first();

    $response = $this->actingAs($vendor, 'vendor')
        ->put(route('vendor.bids.update', $bid), [
            'boq_prices' => [
                ['boq_item_id' => $item->id, 'unit_price' => 100.50, 'total_price' => 201.00],
            ],
            'technical_notes' => 'v1 notes',
        ]);

    $response->assertRedirect();
    $price = BidBoqPrice::where('bid_id', $bid->id)->where('boq_item_id', $item->id)->first();
    expect($price)->not->toBeNull();
    expect($price->unit_price)->toEqual('100.5000');
    expect($bid->fresh()->technical_notes)->toBe('v1 notes');
});

test('submit transitions a draft with prices to submitted', function () {
    [$vendor, $tender] = bidFixture();
    $bid = draftBid($vendor, $tender);
    foreach ($tender->boqSections->first()->items as $item) {
        BidBoqPrice::create([
            'bid_id' => $bid->id,
            'boq_item_id' => $item->id,
            'unit_price' => 50,
            'total_price' => 50 * (float) $item->quantity,
        ]);
    }

    $response = $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.bids.submit', $bid));

    $response->assertRedirect(route('vendor.bids.show', $bid));
    $bid->refresh();
    expect($bid->status)->toBe(BidStatus::Submitted);
    expect($bid->is_sealed)->toBeTrue();
    expect($bid->submitted_at)->not->toBeNull();
});

test('show URL still works after submit (universal-page invariant)', function () {
    [$vendor, $tender] = bidFixture();
    $bid = Bid::factory()->submitted()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
    ]);

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.bids.show', $bid))
        ->assertOk();
});

test('update on a submitted bid is forbidden by policy', function () {
    [$vendor, $tender] = bidFixture();
    $bid = Bid::factory()->submitted()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
    ]);
    $item = $tender->boqSections->first()->items->first();

    $this->actingAs($vendor, 'vendor')
        ->put(route('vendor.bids.update', $bid), [
            'boq_prices' => [
                ['boq_item_id' => $item->id, 'unit_price' => 999, 'total_price' => 999],
            ],
        ])
        ->assertForbidden();
});

test('withdraw on a draft is forbidden — only submitted bids can be withdrawn', function () {
    [$vendor, $tender] = bidFixture();
    $bid = draftBid($vendor, $tender);

    $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.bids.withdraw', $bid), ['reason' => 'nope'])
        ->assertForbidden();
});

test('withdraw on a submitted bid transitions status and clears submit state', function () {
    [$vendor, $tender] = bidFixture();
    $bid = Bid::factory()->submitted()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.bids.withdraw', $bid), ['reason' => 'Pricing error']);

    $response->assertRedirect(route('vendor.bids.index'));
    expect($bid->fresh()->status)->toBe(BidStatus::Withdrawn);
    expect($bid->fresh()->withdrawal_reason)->toBe('Pricing error');
});

test('non-owner vendor cannot view another vendor bid', function () {
    [$ownerVendor, $tender] = bidFixture();
    $bid = draftBid($ownerVendor, $tender);

    $otherVendor = Vendor::factory()->qualified()->create(['is_active' => true]);

    $this->actingAs($otherVendor, 'vendor')
        ->get(route('vendor.bids.show', $bid))
        ->assertForbidden();
});

test('vendor.bids.create reuses existing draft and redirects to show', function () {
    [$vendor, $tender] = bidFixture();
    $existing = draftBid($vendor, $tender);

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.bids.create', $tender))
        ->assertRedirect(route('vendor.bids.show', $existing));
});

test('vendor.bids.create reuses an existing WITHDRAWN bid instead of inserting a duplicate (BUG-19)', function () {
    [$vendor, $tender] = bidFixture();
    $withdrawn = Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'status' => BidStatus::Withdrawn,
        'is_sealed' => false,
        'submitted_at' => now()->subDay(),
        'withdrawal_reason' => 'pricing error',
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.bids.create', $tender));

    // Lands on the withdrawn bid's Show page — universal page handles read-only.
    $response->assertRedirect(route('vendor.bids.show', $withdrawn));
    // Pre-BUG-19 the controller would have INSERTed a second bid here.
    expect(Bid::where('tender_id', $tender->id)->where('vendor_id', $vendor->id)->count())->toBe(1);
});

test('validateSubmissionAllowed rejects when vendor has a withdrawn bid (BUG-19)', function () {
    [$vendor, $tender] = bidFixture();
    Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'status' => BidStatus::Withdrawn,
        'is_sealed' => false,
        'withdrawal_reason' => 'pricing error',
    ]);

    expect(fn () => app(BidService::class)->validateSubmissionAllowed($tender, $vendor))
        ->toThrow(RuntimeException::class, __('messages.bid.duplicate'));
});

test('TenderBrowseController exposes existingBid with status, regardless of withdrawn state (BUG-19)', function () {
    [$vendor, $tender] = bidFixture();
    $bid = Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'status' => BidStatus::Withdrawn,
        'is_sealed' => false,
        'withdrawal_reason' => 'pricing error',
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.tenders.show', $tender));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('vendor/Tenders/Show')
        ->where('canBid', false)
        ->where('existingBid.id', $bid->id)
        ->where('existingBid.status', 'withdrawn')
    );
});

test('TenderBrowseController existingBid.status drives the React button label per status (BUG-19)', function () {
    // Drive the prop shape for each status so the React conditional has the data
    // it needs to choose vendor.tender.continue_bid vs vendor.tender.view_bid.
    $cases = [
        [BidStatus::Draft, 'draft'],
        [BidStatus::Submitted, 'submitted'],
        [BidStatus::Withdrawn, 'withdrawn'],
        [BidStatus::Opened, 'opened'],
    ];

    foreach ($cases as [$enumStatus, $expectedString]) {
        [$vendor, $tender] = bidFixture();
        Bid::factory()->create([
            'tender_id' => $tender->id,
            'vendor_id' => $vendor->id,
            'status' => $enumStatus,
            'is_sealed' => $enumStatus !== BidStatus::Draft,
        ]);

        $response = $this->actingAs($vendor, 'vendor')
            ->get(route('vendor.tenders.show', $tender));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('existingBid.status', $expectedString)
        );
    }
});

// Note: the QueryException backstop in BidController::create (catches
// Illuminate\Database\QueryException → translated toast → redirect) is
// verified by code review only. Pest can't reliably trigger the catch
// block without bypassing the service-level guard via a fragile partial
// mock, and the catch is straightforward boilerplate (report + flash +
// redirect). Defensive layer for the case where a future refactor drifts
// the application guards out of sync with the DB unique constraint.

// ── BUG-18 Sub-phase A: bid documents + envelope split ──────────────────────

function twoEnvelopeBidFixture(): array
{
    $tender = Tender::factory()
        ->published()
        ->withBoq(1, 1)
        ->withCategories(1)
        ->create(['is_two_envelope' => true, 'technical_pass_score' => 70]);
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $vendor->categories()->attach($tender->categories()->first()->id);

    return [$vendor, $tender];
}

test('createDraft always sets envelope_type=single regardless of tender mode (BUG-18 sub-A #1)', function () {
    [$vendor1, $tender1] = bidFixture();
    [$vendor2, $tender2] = twoEnvelopeBidFixture();

    $singleBid = app(BidService::class)->createDraft($tender1, $vendor1);
    $twoBid = app(BidService::class)->createDraft($tender2, $vendor2);

    expect($singleBid->envelope_type)->toBe(EnvelopeType::Single);
    expect($twoBid->envelope_type)->toBe(EnvelopeType::Single);
});

test('show payload includes tender.is_two_envelope and grouped documents (BUG-18 sub-A #2)', function () {
    [$vendor, $tender] = twoEnvelopeBidFixture();
    $bid = draftBid($vendor, $tender);

    BidDocument::create([
        'bid_id' => $bid->id,
        'title' => 'Methodology',
        'original_filename' => 'methodology.pdf',
        'file_path' => 'fake/path/methodology.pdf',
        'file_size' => 12345,
        'mime_type' => 'application/pdf',
        'doc_type' => BidDocType::MethodStatement->value,
        'envelope_type' => EnvelopeType::Technical->value,
        'uploaded_by_vendor_id' => $vendor->id,
        'uploaded_at' => now(),
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.bids.show', $bid));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('tender.is_two_envelope', true)
        ->where('canManageDocuments', true)
        ->has('documents.technical', 1)
        ->has('documents.financial', 0)
        ->has('documents.single', 0)
        ->where('documents.technical.0.title', 'Methodology')
        ->where('documents.technical.0.envelope_type', 'technical')
    );
});

test('storeDocument uploads a PDF and persists with correct envelope (BUG-18 sub-A #3)', function () {
    Storage::fake('s3');
    [$vendor, $tender] = twoEnvelopeBidFixture();
    $bid = draftBid($vendor, $tender);

    $file = UploadedFile::fake()->create('proposal.pdf', 200, 'application/pdf');

    $response = $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.bids.documents.store', $bid), [
            'file' => $file,
            'title' => 'Technical Proposal v1',
            'envelope_type' => 'technical',
            'doc_type' => BidDocType::TechnicalProposal->value,
        ]);

    $response->assertRedirect();
    $doc = BidDocument::where('bid_id', $bid->id)->first();
    expect($doc)->not->toBeNull();
    expect($doc->envelope_type)->toBe('technical');
    expect($doc->original_filename)->toBe('proposal.pdf');
    expect($doc->uploaded_by_vendor_id)->toBe($vendor->id);
    Storage::disk('s3')->assertExists($doc->file_path);
});

test('storeDocument rejects non-PDF (BUG-18 sub-A #4)', function () {
    Storage::fake('s3');
    [$vendor, $tender] = twoEnvelopeBidFixture();
    $bid = draftBid($vendor, $tender);

    $file = UploadedFile::fake()->create('proposal.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $response = $this->actingAs($vendor, 'vendor')
        ->from(route('vendor.bids.show', $bid))
        ->post(route('vendor.bids.documents.store', $bid), [
            'file' => $file,
            'title' => 'Wrong format',
            'envelope_type' => 'technical',
            'doc_type' => BidDocType::TechnicalProposal->value,
        ]);

    $response->assertSessionHasErrors('file');
    expect(BidDocument::where('bid_id', $bid->id)->count())->toBe(0);
});

test('storeDocument rejects file over 5MB cap (BUG-18 sub-A #5)', function () {
    Storage::fake('s3');
    [$vendor, $tender] = twoEnvelopeBidFixture();
    $bid = draftBid($vendor, $tender);

    // 6 MB > 5 MB cap → mimes:pdf passes but max:5120 fails.
    $file = UploadedFile::fake()->create('huge.pdf', 6144, 'application/pdf');

    $response = $this->actingAs($vendor, 'vendor')
        ->from(route('vendor.bids.show', $bid))
        ->post(route('vendor.bids.documents.store', $bid), [
            'file' => $file,
            'title' => 'Too big',
            'envelope_type' => 'technical',
            'doc_type' => BidDocType::TechnicalProposal->value,
        ]);

    $response->assertSessionHasErrors('file');
    expect(BidDocument::where('bid_id', $bid->id)->count())->toBe(0);
});

test('non-owner vendor cannot upload to another vendor bid (BUG-18 sub-A #6)', function () {
    Storage::fake('s3');
    [$ownerVendor, $tender] = twoEnvelopeBidFixture();
    $bid = draftBid($ownerVendor, $tender);

    $otherVendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $file = UploadedFile::fake()->create('tryme.pdf', 100, 'application/pdf');

    $this->actingAs($otherVendor, 'vendor')
        ->post(route('vendor.bids.documents.store', $bid), [
            'file' => $file,
            'title' => 'Sneaky',
            'envelope_type' => 'technical',
            'doc_type' => BidDocType::TechnicalProposal->value,
        ])
        ->assertForbidden();

    expect(BidDocument::where('bid_id', $bid->id)->count())->toBe(0);
});

test('destroyDocument removes a doc on a draft bid for the owner (BUG-18 sub-A #7)', function () {
    Storage::fake('s3');
    [$vendor, $tender] = twoEnvelopeBidFixture();
    $bid = draftBid($vendor, $tender);

    Storage::disk('s3')->put('seeded.pdf', 'fake-bytes');
    $doc = BidDocument::create([
        'bid_id' => $bid->id,
        'title' => 'Goes away',
        'original_filename' => 'goes-away.pdf',
        'file_path' => 'seeded.pdf',
        'file_size' => 100,
        'mime_type' => 'application/pdf',
        'doc_type' => BidDocType::Other->value,
        'envelope_type' => EnvelopeType::Technical->value,
        'uploaded_by_vendor_id' => $vendor->id,
        'uploaded_at' => now(),
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->delete(route('vendor.bids.documents.destroy', [$bid, $doc]));

    $response->assertRedirect();
    expect(BidDocument::find($doc->id))->toBeNull();
    Storage::disk('s3')->assertMissing('seeded.pdf');
});

test('two-envelope submit fails without a technical document (BUG-18 sub-A #8)', function () {
    Storage::fake('s3');
    [$vendor, $tender] = twoEnvelopeBidFixture();
    $bid = draftBid($vendor, $tender);

    // Price the BOQ so the only failing guard is the technical-doc check.
    foreach ($tender->boqSections->first()->items as $item) {
        BidBoqPrice::create([
            'bid_id' => $bid->id,
            'boq_item_id' => $item->id,
            'unit_price' => 50,
            'total_price' => 50 * (float) $item->quantity,
        ]);
    }

    $response = $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.bids.submit', $bid));

    $response->assertRedirect();
    expect(session('errors')?->getBag('default')->any() ?? false)->toBeFalse();
    // Bid stays Draft because the service threw and the controller redirected back.
    // The translated toast itself goes through Inertia::flash and isn't directly
    // observable via session()->get('toast'); the status check is the contract.
    expect($bid->fresh()->status)->toBe(BidStatus::Draft);
    expect($bid->fresh()->is_sealed)->toBeFalse();
});

test('two-envelope submit succeeds when a technical document exists (BUG-18 sub-A #9)', function () {
    Storage::fake('s3');
    [$vendor, $tender] = twoEnvelopeBidFixture();
    $bid = draftBid($vendor, $tender);

    foreach ($tender->boqSections->first()->items as $item) {
        BidBoqPrice::create([
            'bid_id' => $bid->id,
            'boq_item_id' => $item->id,
            'unit_price' => 50,
            'total_price' => 50 * (float) $item->quantity,
        ]);
    }

    BidDocument::create([
        'bid_id' => $bid->id,
        'title' => 'Tech proposal',
        'original_filename' => 'tech.pdf',
        'file_path' => 'fake/tech.pdf',
        'file_size' => 100,
        'mime_type' => 'application/pdf',
        'doc_type' => BidDocType::TechnicalProposal->value,
        'envelope_type' => EnvelopeType::Technical->value,
        'uploaded_by_vendor_id' => $vendor->id,
        'uploaded_at' => now(),
    ]);

    $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.bids.submit', $bid))
        ->assertRedirect(route('vendor.bids.show', $bid));

    expect($bid->fresh()->status)->toBe(BidStatus::Submitted);
});

test('downloadDocument allows owner; denies non-owner (BUG-18 sub-A #10)', function () {
    Storage::fake('s3');
    [$ownerVendor, $tender] = twoEnvelopeBidFixture();
    $bid = draftBid($ownerVendor, $tender);

    Storage::disk('s3')->put('downloadable.pdf', 'fake-pdf-bytes');
    $doc = BidDocument::create([
        'bid_id' => $bid->id,
        'title' => 'Downloadable',
        'original_filename' => 'downloadable.pdf',
        'file_path' => 'downloadable.pdf',
        'file_size' => 13,
        'mime_type' => 'application/pdf',
        'doc_type' => BidDocType::Other->value,
        'envelope_type' => EnvelopeType::Technical->value,
        'uploaded_by_vendor_id' => $ownerVendor->id,
        'uploaded_at' => now(),
    ]);

    $this->actingAs($ownerVendor, 'vendor')
        ->get(route('vendor.bids.documents.download', [$bid, $doc]))
        ->assertOk();

    $otherVendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $this->actingAs($otherVendor, 'vendor')
        ->get(route('vendor.bids.documents.download', [$bid, $doc]))
        ->assertForbidden();
});
