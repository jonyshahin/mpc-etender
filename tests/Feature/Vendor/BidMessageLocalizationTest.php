<?php

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\BidBoqPrice;
use App\Models\BoqItem;
use App\Models\Tender;
use App\Models\Vendor;
use App\Services\BidService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * BidService refuses a bid for three reasons the vendor is meant to read and
 * act on. All three were raised as English string literals and flashed
 * verbatim into the vendor's toast, so an Arabic-reading vendor got
 * "All unit prices must be greater than zero." with no way to change that.
 */

/** @return array{0: Vendor, 1: Tender, 2: Bid} */
function localizationFixture(): array
{
    $tender = Tender::factory()
        ->published()
        ->withBoq(1, 2)
        ->withCategories(1)
        ->create(['is_two_envelope' => false]);

    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $vendor->categories()->attach($tender->categories()->first()->id);

    $bid = Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'status' => BidStatus::Draft,
        'is_sealed' => false,
        'total_amount' => null,
        'encrypted_pricing_data' => null,
        'submitted_at' => null,
    ]);

    return [$vendor, $tender, $bid];
}

test('the incomplete-pricing refusal is translatable', function () {
    [, $tender, $bid] = localizationFixture();

    // One of the two items priced.
    $item = BoqItem::whereIn('section_id', $tender->boqSections()->pluck('id'))->first();
    BidBoqPrice::factory()->create([
        'bid_id' => $bid->id,
        'boq_item_id' => $item->id,
        'unit_price' => 10,
        'total_price' => 100,
    ]);

    app()->setLocale('ar');

    try {
        app(BidService::class)->submit($bid->fresh());
        $this->fail('Expected the incomplete bid to be refused.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->not->toContain('All BOQ items must be priced')
            ->toBe(__('messages.bid.items_unpriced', ['priced' => 1, 'total' => 2]));
    }
});

test('the non-positive price refusal is translatable', function () {
    [, $tender, $bid] = localizationFixture();

    foreach (BoqItem::whereIn('section_id', $tender->boqSections()->pluck('id'))->get() as $index => $item) {
        BidBoqPrice::factory()->create([
            'bid_id' => $bid->id,
            'boq_item_id' => $item->id,
            'unit_price' => $index === 0 ? 0 : 10,
            'total_price' => 0,
        ]);
    }

    app()->setLocale('ar');

    try {
        app(BidService::class)->submit($bid->fresh());
        $this->fail('Expected the zero-priced bid to be refused.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->not->toContain('must be greater than zero')
            ->toBe(__('messages.bid.price_not_positive'));
    }
});

test('the late-withdrawal refusal is translatable', function () {
    [, $tender, $bid] = localizationFixture();
    $bid->update(['status' => BidStatus::Submitted]);
    $tender->update(['submission_deadline' => now()->subDay()]);

    app()->setLocale('ar');

    try {
        app(BidService::class)->withdraw($bid->fresh(), 'Changed our mind.');
        $this->fail('Expected the late withdrawal to be refused.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->not->toContain('Cannot withdraw after submission deadline')
            ->toBe(__('messages.bid.withdraw_after_deadline'));
    }
});

test('every vendor-facing bid message exists in all three locales', function () {
    $keys = [
        'messages.bid.items_unpriced',
        'messages.bid.price_not_positive',
        'messages.bid.withdraw_after_deadline',
    ];

    foreach (['en', 'ar', 'ku'] as $locale) {
        $catalogue = json_decode(file_get_contents(lang_path("{$locale}.json")), true);

        foreach ($keys as $key) {
            // toHaveKey's second argument is the expected value, not a message,
            // so the key name goes in the assertion description instead.
            expect(array_key_exists($key, $catalogue))
                ->toBeTrue("{$key} missing from {$locale}.json");
        }
    }
});
