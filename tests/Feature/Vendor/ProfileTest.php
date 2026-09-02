<?php

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── What the page ships ──

test('the profile does not ship the identity of the MPC user who qualified the vendor', function () {
    $reviewer = User::factory()->create();
    $vendor = Vendor::factory()->qualified()->create([
        'is_active' => true,
        'qualified_by' => $reviewer->id,
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.profile.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // The page passed the whole Vendor model, so every column bar the
        // password went over the wire — including which MPC user signed the
        // vendor off, which is internal staffing, not the vendor's record.
        ->missing('vendor.qualified_by')
        ->missing('vendor.must_change_password')
        ->missing('vendor.last_login_at')
        ->missing('vendor.is_active')
        ->missing('vendor.password')
        ->missing('vendor.remember_token')
    );
});

test('the profile still carries the fields the form edits', function () {
    $vendor = Vendor::factory()->qualified()->create([
        'company_name' => 'Al-Rashid Contracting',
        'company_name_ar' => 'الرشيد للمقاولات',
        'language_pref' => 'ar',
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.profile.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('vendor.company_name', 'Al-Rashid Contracting')
        ->where('vendor.company_name_ar', 'الرشيد للمقاولات')
        ->where('vendor.language_pref', 'ar')
        ->has('vendor.trade_license_no')
        ->has('vendor.contact_person')
        ->has('vendor.email')
        ->has('vendor.phone')
        ->has('vendor.whatsapp_number')
        ->has('vendor.address')
        ->has('vendor.city')
        ->has('vendor.country')
        ->has('vendor.website')
    );
});

// ── The one thing the page never told the vendor ──

test('the profile tells the vendor whether they may bid', function () {
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.profile.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // Shipped and never rendered: the vendor could read their own address
        // back but not whether they were qualified to bid.
        ->where('standing.status', VendorStatus::Qualified->value)
        ->where('standing.can_bid', true)
        ->has('standing.status_label_key')
        ->has('standing.qualified_at')
    );
});

test('a rejected vendor is told the reason on their own profile', function () {
    $vendor = Vendor::factory()->rejected()->create([
        'is_active' => true,
        'rejection_reason' => 'Trade licence had lapsed at the time of review.',
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.profile.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('standing.status', VendorStatus::Rejected->value)
        ->where('standing.can_bid', false)
        ->where('standing.reason', 'Trade licence had lapsed at the time of review.')
    );
});

test('a suspended vendor is not told they may bid', function () {
    $vendor = Vendor::factory()->qualified()->create(['is_active' => false]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.profile.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('standing.can_bid', false));
});

test('a reason is only shown when there is one to give', function () {
    $vendor = Vendor::factory()->qualified()->create([
        'is_active' => true,
        'rejection_reason' => null,
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.profile.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('standing.reason', null));
});

// ── The language picker, which had three copies of its options ──

test('the language options come from the app locale list', function () {
    $vendor = Vendor::factory()->qualified()->create();

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.profile.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // Hardcoded in the select, restated as in:en,ar,ku in validation, and
        // listed a third time in lib/locales. Any two could drift.
        ->has('languageOptions', 3)
        ->where('languageOptions.0.value', 'en')
    );
});

// ── Authorization the form must keep ──

test('a vendor cannot promote themselves through the profile form', function () {
    $vendor = Vendor::factory()->create([
        'prequalification_status' => VendorStatus::Pending,
        'is_active' => false,
    ]);

    $this->actingAs($vendor, 'vendor')->put(route('vendor.profile.update'), [
        'company_name' => 'Al-Rashid Contracting',
        'trade_license_no' => 'TL-1234',
        'contact_person' => 'A. Rashid',
        'email' => 'contact@rashid.test',
        'phone' => '+964 770 000 0000',
        'address' => '12 Karrada St',
        'city' => 'Baghdad',
        'country' => 'Iraq',
        'prequalification_status' => VendorStatus::Qualified->value,
        'is_active' => true,
    ]);

    $vendor->refresh();

    expect($vendor->prequalification_status)->toBe(VendorStatus::Pending)
        ->and($vendor->is_active)->toBeFalse()
        ->and($vendor->company_name)->toBe('Al-Rashid Contracting');
});
