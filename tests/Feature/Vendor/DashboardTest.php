<?php

use App\Enums\BidStatus;
use App\Enums\TenderStatus;
use App\Enums\VendorDocStatus;
use App\Enums\VendorStatus;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Tender;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A qualified vendor in one category, and a published tender in that category.
 *
 * @return array{0: Vendor, 1: Tender, 2: Category}
 */
function dashboardFixture(array $vendorAttributes = []): array
{
    $tender = Tender::factory()->published()->withCategories(1)->create();
    $category = $tender->categories()->first();

    $vendor = Vendor::factory()->qualified()->create([
        'is_active' => true,
        ...$vendorAttributes,
    ]);
    $vendor->categories()->attach($category->id);

    return [$vendor, $tender, $category];
}

// ── Standing, which the page only half told the vendor about ──

test('the dashboard tells a suspended vendor they cannot bid', function () {
    [$vendor] = dashboardFixture([
        'prequalification_status' => VendorStatus::Suspended,
        'is_active' => false,
        'rejection_reason' => 'Trade licence lapsed.',
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // The banner only ever fired for 'pending' and 'rejected', compared as
        // string literals — so suspended, blacklisted and under_review vendors
        // saw an ordinary-looking dashboard.
        ->where('standing.can_bid', false)
        ->where('standing.status', VendorStatus::Suspended->value)
        ->where('standing.reason', 'Trade licence lapsed.')
        ->has('standing.status_label_key')
    );
});

test('the dashboard agrees with the profile about eligibility', function () {
    [$vendor] = dashboardFixture();

    $dashboard = $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'));
    $profile = $this->actingAs($vendor, 'vendor')->get(route('vendor.profile.edit'));

    $dashboard->assertInertia(fn ($page) => $page->where('standing.can_bid', true));
    $profile->assertInertia(fn ($page) => $page->where('standing.can_bid', true));
});

test('an under-review vendor is told so rather than shown nothing', function () {
    [$vendor] = dashboardFixture([
        'prequalification_status' => VendorStatus::UnderReview,
        'is_active' => true,
    ]);

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('standing.can_bid', false)
            ->where('standing.status', VendorStatus::UnderReview->value)
        );
});

// ── The headline figures the page had none of ──

test('the dashboard leads with the counts the vendor can act on', function () {
    [$vendor, $tender] = dashboardFixture();

    Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'status' => BidStatus::Draft,
    ]);
    VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        'status' => VendorDocStatus::Approved,
        'expiry_date' => now()->subDay(),
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('summary.open_tenders', 1)
        ->where('summary.draft_bids', 1)
        ->where('summary.submitted_bids', 0)
        ->where('summary.documents_needing_attention', 1)
    );
});

// ── Open tenders ──

test('a tender that is not published never reaches the dashboard', function () {
    [$vendor, , $category] = dashboardFixture();

    // The controller matched on the literal 'published' rather than the enum.
    // Both agree today; the test is what keeps them agreeing.
    Tender::factory()->create([
        'status' => TenderStatus::SubmissionClosed,
        'submission_deadline' => now()->addWeek(),
    ])->categories()->attach($category->id);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('openTenders', 1));
});

test('an open tender says whether the vendor already has a bid on it', function () {
    [$vendor, $tender] = dashboardFixture();
    $bid = Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'status' => BidStatus::Draft,
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // Without this the card cannot tell "start a bid" from "finish the one
        // you started", which is the difference that matters before a deadline.
        ->where('openTenders.0.my_bid.id', $bid->id)
        ->where('openTenders.0.my_bid.is_editable', true)
    );
});

test('a rival bid never reaches the dashboard', function () {
    [$vendor, $tender] = dashboardFixture();
    Bid::factory()->submitted()->create(['tender_id' => $tender->id]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('openTenders.0.my_bid', null)
        ->has('recentBids', 0)
    );
});

// ── Documents ──

test('the dashboard and the documents page count attention the same way', function () {
    [$vendor] = dashboardFixture();

    VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        'status' => VendorDocStatus::Rejected,
        'expiry_date' => now()->addYears(2),
    ]);
    VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        'status' => VendorDocStatus::Approved,
        'expiry_date' => now()->addDays(10),
    ]);

    $dashboard = $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'));
    $documents = $this->actingAs($vendor, 'vendor')->get(route('vendor.documents.index'));

    // A rejected document counts on the documents page and did not on the
    // dashboard, which looked only at expiry_date.
    $dashboard->assertInertia(fn ($page) => $page->where('summary.documents_needing_attention', 2));
    $documents->assertInertia(fn ($page) => $page->where('summary.needs_attention', 2));
});

test('the dashboard does not ship document bucket keys', function () {
    [$vendor] = dashboardFixture();
    VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        'expiry_date' => now()->subDay(),
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documentAlerts', 1)
        ->missing('documentAlerts.0.file_path')
    );
});
