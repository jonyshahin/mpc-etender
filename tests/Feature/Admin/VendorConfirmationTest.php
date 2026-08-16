<?php

use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function adminForConfirmation(string ...$slugs): User
{
    $role = Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);

    foreach ($slugs as $slug) {
        $permission = Permission::create([
            'name' => ucwords(str_replace('.', ' ', $slug)),
            'slug' => $slug,
            'module' => explode('.', $slug)[0],
        ]);
        $role->permissions()->attach($permission->id);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

test('it renders the confirmation sheet with the vendor details', function () {
    $admin = adminForConfirmation('vendors.view');
    $category = Category::factory()->create(['name_en' => 'Civil Works']);
    $vendor = Vendor::factory()->create(['company_name' => 'Zagros Construction LLC']);
    $vendor->categories()->attach($category->id);

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Vendors/Confirmation')
            ->where('vendor.id', $vendor->id)
            ->where('vendor.company_name', 'Zagros Construction LLC')
            ->has('vendor.categories', 1)
            ->has('qrCode')
            ->has('websiteUrl')
            ->has('companyName')
            ->has('generatedAt')
        );
});

test('the sheet carries no password hash', function () {
    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('vendor.password'));
});

test('the QR code is an inline SVG data URI', function () {
    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.vendors.confirmation', $vendor));

    $qr = $response->viewData('page')['props']['qrCode'];

    expect($qr)->toStartWith('data:image/svg+xml;base64,');

    $svg = base64_decode(substr($qr, strlen('data:image/svg+xml;base64,')));
    expect($svg)->toContain('<svg');
    // The XML prolog must be stripped or the data URI fails to render as an <img>.
    expect($svg)->not->toStartWith('<?xml');
});

test('the QR target falls back to APP_URL when no website is configured', function () {
    config(['app.url' => 'https://tenders.mpc-group.com']);

    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertInertia(fn (Assert $page) => $page->where('websiteUrl', 'https://tenders.mpc-group.com'));
});

test('a configured general.website_url overrides APP_URL', function () {
    config(['app.url' => 'https://tenders.mpc-group.com']);

    SystemSetting::create([
        'key' => 'general.website_url',
        'value' => 'https://www.mpc-group.com',
        'group' => 'general',
        'type' => 'string',
        'description' => 'Public website',
    ]);

    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertInertia(fn (Assert $page) => $page->where('websiteUrl', 'https://www.mpc-group.com'));
});

test('a blank general.website_url still falls back to APP_URL', function () {
    config(['app.url' => 'https://tenders.mpc-group.com']);

    SystemSetting::create([
        'key' => 'general.website_url',
        'value' => '',
        'group' => 'general',
        'type' => 'string',
        'description' => 'Public website',
    ]);

    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertInertia(fn (Assert $page) => $page->where('websiteUrl', 'https://tenders.mpc-group.com'));
});

test('an admin without vendors.view cannot open the sheet', function () {
    $admin = adminForConfirmation('vendors.create');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertForbidden();
});

test('the QR encodes non-Latin-1 URLs instead of throwing', function () {
    $service = app(QrCodeService::class);

    // BaconQrCode's writeString() defaults to ISO-8859-1, which throws outright
    // on these. This is an Arabic/Kurdish deployment, so an Arabic IDN or query
    // string is realistic — and an em dash pasted from Word triggers it by
    // accident. Each case 500'd the confirmation page before the UTF-8 fix.
    $payloads = [
        'https://مپک.عراق',
        'https://example.com/?q=كردستان',
        'https://example.com/a—b',
        'https://café.com',
    ];

    foreach ($payloads as $payload) {
        expect($service->svg($payload))->toContain('<svg');
    }
});

test('a non-Latin-1 configured website renders the sheet rather than a 500', function () {
    SystemSetting::create([
        'key' => 'general.website_url',
        'value' => 'https://example.com/?q=كردستان',
        'group' => 'general',
        'type' => 'string',
        'description' => 'Public website',
    ]);

    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertOk();
});

test('the sheet still renders when APP_URL is blank', function () {
    // A bare `APP_URL=` in .env makes config('app.url') an empty string rather
    // than its default, and an empty QR payload throws.
    config(['app.url' => '']);

    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('websiteUrl', fn ($url) => filled($url)));
});

test('the QR service rejects an empty payload with a clear error', function () {
    expect(fn () => app(QrCodeService::class)->svg('   '))
        ->toThrow(InvalidArgumentException::class, 'Cannot render a QR code for an empty payload.');
});

test('the QR service encodes arbitrary payloads as SVG', function () {
    $svg = app(QrCodeService::class)->svg('https://example.test/abc');

    expect($svg)->toContain('<svg')
        ->and($svg)->not->toStartWith('<?xml')
        // A rendered QR is made of rects/paths; an empty document would mean the
        // writer silently produced nothing.
        ->and(strlen($svg))->toBeGreaterThan(200);
});
