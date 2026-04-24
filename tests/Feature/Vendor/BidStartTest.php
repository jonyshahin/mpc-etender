<?php

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BidService;
use App\Services\VendorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Create a published tender and a vendor that share one category, so the
 * category-match branch of validateSubmissionAllowed passes. Used by tests
 * that want the OTHER branches in focus.
 */
function matchedCategoryTender(Vendor $vendor): Tender
{
    $category = Category::factory()->create();
    $vendor->categories()->attach($category);

    $tender = Tender::factory()->published()->create();
    $tender->categories()->attach($category);

    return $tender;
}

test('qualified + active vendor with matching category can start a bid', function () {
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $tender = matchedCategoryTender($vendor);

    $bid = app(BidService::class)->createDraft($tender, $vendor);

    expect($bid->tender_id)->toBe($tender->id);
    expect($bid->vendor_id)->toBe($vendor->id);
});

test('qualified but inactive vendor is blocked with vendor_not_active', function () {
    $vendor = Vendor::factory()->qualified()->create(['is_active' => false]);
    $tender = matchedCategoryTender($vendor);

    expect(fn () => app(BidService::class)->validateSubmissionAllowed($tender, $vendor))
        ->toThrow(RuntimeException::class, __('messages.bid.vendor_not_active'));
});

test('pending vendor is blocked with vendor_not_qualified', function () {
    $vendor = Vendor::factory()->create(); // default: Pending, is_active=true
    $tender = matchedCategoryTender($vendor);

    expect(fn () => app(BidService::class)->validateSubmissionAllowed($tender, $vendor))
        ->toThrow(RuntimeException::class, __('messages.bid.vendor_not_qualified'));
});

test('suspend then re-approve restores is_active and allows bidding (BUG-09)', function () {
    $reviewer = User::factory()->create();
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $tender = matchedCategoryTender($vendor);

    // Suspension flips is_active=false.
    app(VendorService::class)->suspend($vendor, $reviewer, 'Documents expired');
    $vendor->refresh();
    expect($vendor->prequalification_status)->toBe(VendorStatus::Suspended);
    expect($vendor->is_active)->toBeFalse();

    // Re-approving must restore is_active=true — the core BUG-09 fix.
    app(VendorService::class)->prequalify($vendor, $reviewer);
    $vendor->refresh();
    expect($vendor->prequalification_status)->toBe(VendorStatus::Qualified);
    expect($vendor->is_active)->toBeTrue();

    // …and the bid-start guard now passes for a round-tripped vendor.
    $bid = app(BidService::class)->createDraft($tender, $vendor);
    expect($bid->vendor_id)->toBe($vendor->id);
});

test('qualified vendor without matching category is blocked with category_mismatch', function () {
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $vendor->categories()->attach(Category::factory()->create());

    $tender = Tender::factory()->published()->create();
    $tender->categories()->attach(Category::factory()->create());

    expect(fn () => app(BidService::class)->validateSubmissionAllowed($tender, $vendor))
        ->toThrow(RuntimeException::class, __('messages.bid.category_mismatch'));
});
