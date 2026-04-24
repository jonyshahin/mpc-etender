<?php

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\BidBoqPrice;
use App\Models\Tender;
use App\Models\Vendor;
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
