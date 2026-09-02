<?php

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\Tender;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A qualified vendor with one submitted bid on a published tender.
 *
 * @return array{0: Vendor, 1: Tender, 2: Bid}
 */
function bidIndexFixture(): array
{
    $tender = Tender::factory()->published()->withCategories(1)->create();
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $vendor->categories()->attach($tender->categories()->first()->id);

    $bid = Bid::factory()->submitted()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
    ]);

    return [$vendor, $tender, $bid];
}

// ── What the list ships ──

test('the bid list does not ship submission forensics', function () {
    [$vendor, , $bid] = bidIndexFixture();
    $bid->forceFill([
        'submission_ip' => '10.1.2.3',
        'submission_user_agent' => 'Mozilla/5.0 (Windows NT 10.0)',
    ])->save();

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.bids.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('bids.data', 1)
        ->missing('bids.data.0.submission_ip')
        ->missing('bids.data.0.submission_user_agent')
        ->missing('bids.data.0.encrypted_pricing_data')
        ->missing('bids.data.0.opened_by')
    );
});

test('the bid list still carries the vendor own total', function () {
    [$vendor, , $bid] = bidIndexFixture();

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.bids.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('bids.data.0.total_amount', (string) $bid->total_amount)
    );
});

test('the bid list carries the tender deadline so a row can show it', function () {
    [$vendor] = bidIndexFixture();

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.bids.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('bids.data.0.tender.submission_deadline')
        ->has('bids.data.0.tender.title_ar')
    );
});

test('one vendor never sees another vendor bid', function () {
    [$vendor, $tender] = bidIndexFixture();
    Bid::factory()->submitted()->create(['tender_id' => $tender->id]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.bids.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('bids.data', 1));
});

// ── Filtering, sorting and search ──

test('the bid list can be filtered by status', function () {
    [$vendor, $tender] = bidIndexFixture();
    Bid::factory()->create([
        'tender_id' => Tender::factory()->published()->create()->id,
        'vendor_id' => $vendor->id,
        'status' => BidStatus::Draft,
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.bids.index', ['status' => BidStatus::Draft->value]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('bids.data', 1)
        ->where('bids.data.0.status', 'draft')
    );
});

test('the bid list can be searched by reference', function () {
    [$vendor] = bidIndexFixture();
    Bid::factory()->create([
        'tender_id' => Tender::factory()->published()->create()->id,
        'vendor_id' => $vendor->id,
        'bid_reference' => 'TND-ZZ-9999-B001',
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.bids.index', ['search' => 'ZZ-9999']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('bids.data', 1)
        ->where('bids.data.0.bid_reference', 'TND-ZZ-9999-B001')
    );
});

test('the sortable columns the table renders are actually honoured', function () {
    [$vendor] = bidIndexFixture();
    Bid::factory()->create([
        'tender_id' => Tender::factory()->published()->create()->id,
        'vendor_id' => $vendor->id,
        'bid_reference' => 'AAA-000-B001',
        'status' => BidStatus::Draft,
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.bids.index', ['sort' => 'bid_reference', 'direction' => 'asc']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('bids.data.0.bid_reference', 'AAA-000-B001')
        ->where('filters.sort', 'bid_reference')
        ->where('filters.direction', 'asc')
    );
});

test('an unknown sort column falls back instead of erroring', function () {
    [$vendor] = bidIndexFixture();

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.bids.index', ['sort' => 'submission_ip', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.sort', 'submitted_at'));
});

test('the bid list exposes status counts and a summary', function () {
    [$vendor] = bidIndexFixture();

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.bids.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('summary.total')
        ->has('summary.drafts')
        ->has('summary.submitted')
        ->has('summary.awaiting_deadline')
        ->has('statusCounts.draft')
        ->has('statusOptions')
    );
});
