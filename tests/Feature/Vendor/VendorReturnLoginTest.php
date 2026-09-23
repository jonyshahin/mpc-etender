<?php

use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * A vendor's second sign-in, not just their first.
 *
 * Reported from production: an admin reset a vendor's password, the vendor
 * signed in once and everything worked, and every sign-in after that failed.
 * The first sign-in is the one the suite covered; this walks the whole journey
 * the vendor actually took, including the forced change in the middle and the
 * return visit that follows it.
 *
 * Helper names in Pest files are global — hence the suffix.
 */
function returningVendor(string $email = 'Returning.Vendor@example.com'): array
{
    $temporary = Str::password(12);

    $vendor = app(VendorService::class)->createByAdmin([
        'company_name' => 'Cool Air HVAC',
        'trade_license_no' => 'TL-'.Str::random(6),
        'address' => 'Street 1',
        'city' => 'Erbil',
        'country' => 'Iraq',
        'contact_person' => 'Contact',
        'email' => $email,
        'phone' => '07700000000',
        'category_ids' => [],
    ], User::factory()->create(), $temporary);

    return [$vendor, $temporary];
}

/** Shaped like the reported one: sixteen characters, symbol at the end. */
const RETURN_PASSWORD = 'Qm7xKpR2wT9vLs4@';

test('a vendor can sign in again after the forced first change', function () {
    [$vendor, $temporary] = returningVendor();

    // First visit: the temporary password, then the forced change.
    $this->post(route('vendor.login.store'), ['email' => $vendor->email, 'password' => $temporary])
        ->assertRedirect(route('vendor.dashboard'));

    $this->put(route('vendor.password.change'), [
        'current_password' => $temporary,
        'password' => RETURN_PASSWORD,
        'password_confirmation' => RETURN_PASSWORD,
    ])->assertSessionHasNoErrors();

    $this->post(route('vendor.logout'));
    $this->assertGuest('vendor');

    // The return visit — the one that was failing.
    $this->post(route('vendor.login.store'), ['email' => $vendor->email, 'password' => RETURN_PASSWORD])
        ->assertRedirect(route('vendor.dashboard'));

    $this->assertAuthenticatedAs($vendor->fresh(), 'vendor');
});

test('the return visit works whatever case the address is typed in', function () {
    [$vendor, $temporary] = returningVendor('Returning.Vendor@example.com');

    $this->post(route('vendor.login.store'), ['email' => $vendor->email, 'password' => $temporary]);
    $this->put(route('vendor.password.change'), [
        'current_password' => $temporary,
        'password' => RETURN_PASSWORD,
        'password_confirmation' => RETURN_PASSWORD,
    ]);
    $this->post(route('vendor.logout'));

    // Phones capitalise the first letter; a desktop does not.
    $this->post(route('vendor.login.store'), [
        'email' => 'returning.vendor@example.com',
        'password' => RETURN_PASSWORD,
    ])->assertRedirect(route('vendor.dashboard'));
})->skip(
    fn () => DB::getDriverName() === 'sqlite',
    // Holds by collation, not by code: nothing normalises the address, and
    // production's utf8mb4_unicode_ci matches it case-insensitively (checked
    // against the live row). SQLite compares case-sensitively, so this only
    // means something when the suite runs on MySQL.
    'case-insensitive email matching is a MySQL collation property',
);
