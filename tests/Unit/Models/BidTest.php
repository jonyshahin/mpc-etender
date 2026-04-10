<?php

use App\Models\Bid;
use App\Models\Role;
use App\Models\Tender;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bid encrypts pricing data on save', function () {
    $role = Role::factory()->create();
    $tender = Tender::factory()->create();
    $vendor = Vendor::factory()->create();

    $bid = Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'encrypted_pricing_data' => '{"total": 500000}',
    ]);

    // The raw DB value should not be the plaintext
    $raw = DB::table('bids')->where('id', $bid->id)->value('encrypted_pricing_data');
    expect($raw)->not->toBe('{"total": 500000}');
    expect($raw)->not->toBeNull();
});

test('bid decrypts pricing data on read', function () {
    $role = Role::factory()->create();
    $tender = Tender::factory()->create();
    $vendor = Vendor::factory()->create();

    $bid = Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'encrypted_pricing_data' => '{"total": 500000}',
    ]);

    $fresh = Bid::find($bid->id);
    expect($fresh->encrypted_pricing_data)->toBe('{"total": 500000}');
});

test('bid null pricing data remains null', function () {
    $role = Role::factory()->create();
    $tender = Tender::factory()->create();
    $vendor = Vendor::factory()->create();

    $bid = Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'encrypted_pricing_data' => null,
    ]);

    $fresh = Bid::find($bid->id);
    expect($fresh->encrypted_pricing_data)->toBeNull();
});
