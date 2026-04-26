<?php

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\BidBoqPrice;
use App\Models\Tender;
use App\Models\Vendor;
use App\Services\BidService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a published tender with one BOQ section+items and one category,
 * plus a qualified vendor matched to that category. Returns [vendor, tender].
 */
function bidFixture(): array
{
    $tender = Tender::factory()->published()->withBoq(1, 2)->withCategories(1)->create();
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
