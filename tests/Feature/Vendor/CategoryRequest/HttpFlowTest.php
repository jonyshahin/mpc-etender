<?php

use App\Models\Category;
use App\Models\Vendor;
use App\Models\VendorCategoryRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->vendor = Vendor::factory()->create();
});

test('VENDOR-01: vendor cannot PUT old /vendor/categories endpoint (removed)', function () {
    // Route is gone — 405 (method not allowed) or 404; either proves removal.
    $response = $this->actingAs($this->vendor, 'vendor')->put('/vendor/categories', [
        'category_ids' => [Category::factory()->create()->id],
    ]);
    expect($response->status())->toBeIn([404, 405]);
});

test('VENDOR-02: vendor can submit a category change request via HTTP', function () {
    $cat = Category::factory()->create();

    $response = $this->actingAs($this->vendor, 'vendor')->post(route('vendor.category-requests.store'), [
        'justification' => 'We have acquired specialized equipment for civil works and would like to bid.',
        'add_categories' => [$cat->id],
        'remove_categories' => [],
        'evidence' => [UploadedFile::fake()->create('license.pdf', 500, 'application/pdf')],
    ]);

    $response->assertRedirect(route('vendor.category-requests.index'));
    expect(VendorCategoryRequest::where('vendor_id', $this->vendor->id)->count())->toBe(1);
});

test('VENDOR-03: submit without evidence fails validation', function () {
    $cat = Category::factory()->create();

    $this->actingAs($this->vendor, 'vendor')
        ->post(route('vendor.category-requests.store'), [
            'justification' => 'A valid-looking justification of sufficient length.',
            'add_categories' => [$cat->id],
        ])
        ->assertSessionHasErrors('evidence');

    expect(VendorCategoryRequest::count())->toBe(0);
});

test('VENDOR-04: vendor cannot view another vendors request', function () {
    $other = Vendor::factory()->create();
    $otherReq = VendorCategoryRequest::query()->create([
        'vendor_id' => $other->id,
        'justification' => 'someone else private',
        'status' => 'pending',
    ]);

    $this->actingAs($this->vendor, 'vendor')
        ->get(route('vendor.category-requests.show', $otherReq))
        ->assertForbidden();
});

test('VENDOR-05: vendor can withdraw own pending request; cannot withdraw approved', function () {
    $req = VendorCategoryRequest::query()->create([
        'vendor_id' => $this->vendor->id,
        'justification' => 'withdrawable',
        'status' => 'pending',
    ]);

    $this->actingAs($this->vendor, 'vendor')
        ->delete(route('vendor.category-requests.destroy', $req), ['reason' => 'Missing cert.'])
        ->assertRedirect(route('vendor.category-requests.index'));

    expect($req->fresh()->status)->toBe('withdrawn');
    expect($req->fresh()->withdraw_reason)->toBe('Missing cert.');

    // Now simulate a different approved request and try to withdraw it
    $approved = VendorCategoryRequest::query()->create([
        'vendor_id' => $this->vendor->id,
        'justification' => 'already decided',
        'status' => 'approved',
    ]);

    $this->actingAs($this->vendor, 'vendor')
        ->delete(route('vendor.category-requests.destroy', $approved))
        ->assertSessionHasErrors('status');

    expect($approved->fresh()->status)->toBe('approved');
});
