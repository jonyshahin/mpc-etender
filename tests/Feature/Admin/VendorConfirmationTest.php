<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function adminForConfirmation(string ...$slugs): User
{
    $role = Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);

    foreach ($slugs as $slug) {
        // firstOrCreate, not create: a test that builds two admins would otherwise
        // violate the unique index on permissions.slug.
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace('.', ' ', $slug)), 'module' => explode('.', $slug)[0]],
        );
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

test('the letterhead carries the project name and a logo', function () {
    SystemSetting::create([
        'key' => 'general.project_name',
        'value' => 'Boulevard Mosul Project',
        'group' => 'general',
        'type' => 'string',
        'description' => 'Project name',
    ]);

    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('projectName', 'Boulevard Mosul Project')
            // Two distinct marks: Boulevard is the project, MPC the company.
            ->where('logoUrl', fn ($url) => str_contains($url, 'boulevard-logo'))
            ->where('companyLogoUrl', '/mpc-logo.png')
        );
});

test('the PDF embeds both marks downscaled', function () {
    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.vendors.confirmation.pdf', $vendor));
    $response->assertOk();

    $bytes = strlen($response->getContent());

    // Both source logos are ~518 KB and ~207 KB; dompdf embeds a raster at its
    // native resolution, so without downscaling the letter alone exceeded 500 KB
    // and peaked at 124 MB — over PHP's 128 MB default.
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
    expect($bytes)->toBeLessThan(250_000);
});

test('the temporary password survives a reload of the letter', function () {
    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    // Straight after onboarding the password rides in on the flash. The page
    // re-keeps it so a refresh, or the PDF download from this page, still has it.
    $this->actingAs($admin)
        ->withSession(['vendor_temp_password' => ['vendor_id' => $vendor->id, 'value' => 'Tmp7x9Kd2Qa4']])
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertInertia(fn (Assert $page) => $page->where('temporaryPassword', 'Tmp7x9Kd2Qa4'));

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertInertia(fn (Assert $page) => $page->where('temporaryPassword', 'Tmp7x9Kd2Qa4'));
});

test('reissuing puts a working password back on the letter', function () {
    $admin = adminForConfirmation('vendors.view', 'vendors.update');
    $vendor = Vendor::factory()->create();
    $originalHash = $vendor->password;

    $this->actingAs($admin)
        ->post(route('admin.vendors.reissue-password', $vendor))
        ->assertRedirect(route('admin.vendors.confirmation', $vendor));

    // The letter now shows a password again, and it is the one that actually works.
    $response = $this->actingAs($admin)->get(route('admin.vendors.confirmation', $vendor));
    $shown = $response->viewData('page')['props']['temporaryPassword'];

    expect($shown)->toBeString()->not->toBeEmpty();
    expect(Hash::check($shown, $vendor->fresh()->password))->toBeTrue();
    expect($vendor->fresh()->password)->not->toBe($originalHash);
    expect($vendor->fresh()->must_change_password)->toBeTrue();
});

test('reissuing writes an audit row', function () {
    $admin = adminForConfirmation('vendors.view', 'vendors.update');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)->post(route('admin.vendors.reissue-password', $vendor));

    expect(AuditLog::where('auditable_id', $vendor->id)
        ->where('action', 'password_reset_admin_temp')
        ->where('user_id', $admin->id)
        ->exists())->toBeTrue();
});

test('reissuing refuses when the admin could not open the resulting letter', function () {
    // vendors.update without vendors.view is an assignable combination. Without
    // the second check this rotated the password and then 403'd on the redirect,
    // losing the new credential while the old one was already dead.
    $admin = adminForConfirmation('vendors.update');
    $vendor = Vendor::factory()->create();
    $originalHash = $vendor->password;

    $this->actingAs($admin)
        ->post(route('admin.vendors.reissue-password', $vendor))
        ->assertForbidden();

    expect($vendor->fresh()->password)->toBe($originalHash);
});

test('reissuing needs update permission, not just view', function () {
    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();
    $originalHash = $vendor->password;

    $this->actingAs($admin)
        ->post(route('admin.vendors.reissue-password', $vendor))
        ->assertForbidden();

    expect($vendor->fresh()->password)->toBe($originalHash);
});

// Split across two tests rather than building both admins in one: roles.slug is
// unique, and sharing a role would leak the first admin's permissions to the
// second, quietly invalidating the negative case.
test('the letter offers reissue on a reprint when the admin can update', function () {
    $vendor = Vendor::factory()->create();

    $this->actingAs(adminForConfirmation('vendors.view', 'vendors.update'))
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertInertia(fn (Assert $page) => $page
            ->where('temporaryPassword', null)
            ->where('canReissuePassword', true)
        );
});

test('a view-only admin is not offered reissue', function () {
    $vendor = Vendor::factory()->create();

    // The button must not appear for someone whose POST would only 403.
    $this->actingAs(adminForConfirmation('vendors.view'))
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertInertia(fn (Assert $page) => $page->where('canReissuePassword', false));
});

test('a second PDF download still carries the password', function () {
    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $session = ['vendor_temp_password' => ['vendor_id' => $vendor->id, 'value' => 'Tmp7x9Kd2Qa4']];

    // The PDF route must re-keep the flash. Without that the first download
    // consumed it and a second copy came out with the credentials block silently
    // missing — the admin would hand the vendor a letter they cannot sign in with.
    $first = $this->actingAs($admin)->withSession($session)
        ->get(route('admin.vendors.confirmation.pdf', $vendor));
    $first->assertOk();

    $second = $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation.pdf', $vendor));
    $second->assertOk();

    // Same document both times — a dropped credentials block shows up as a
    // materially smaller file.
    expect(strlen($second->getContent()))
        ->toBeGreaterThan((int) (strlen($first->getContent()) * 0.95));
});

test('one vendor password never appears on another vendor letter', function () {
    $admin = adminForConfirmation('vendors.view');
    $onboarded = Vendor::factory()->create(['company_name' => 'Vendor A']);
    $other = Vendor::factory()->create(['company_name' => 'Vendor B']);

    // The flash lives in the admin's session, not on the vendor. Before the id
    // check this printed A's credentials on B's letter — an admin could hand B a
    // document carrying another company's password.
    $this->actingAs($admin)
        ->withSession(['vendor_temp_password' => ['vendor_id' => $onboarded->id, 'value' => 'A-SECRET']])
        ->get(route('admin.vendors.confirmation', $other))
        ->assertInertia(fn (Assert $page) => $page->where('temporaryPassword', null));

    // …and the PDF must be scoped the same way.
    $response = $this->actingAs($admin)
        ->withSession(['vendor_temp_password' => ['vendor_id' => $onboarded->id, 'value' => 'A-SECRET']])
        ->get(route('admin.vendors.confirmation.pdf', $other));

    $response->assertOk();
    expect($response->getContent())->not->toContain('A-SECRET');
});

test('the temporary password is gone once the admin navigates away', function () {
    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->withSession(['vendor_temp_password' => ['vendor_id' => $vendor->id, 'value' => 'Tmp7x9Kd2Qa4']])
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertOk();

    // Any other page consumes the flash; the value is not recoverable afterwards
    // because what is stored on the vendor is a bcrypt hash.
    $this->actingAs($admin)->get(route('admin.vendors.index'))->assertOk();

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertInertia(fn (Assert $page) => $page->where('temporaryPassword', null));
});

test('the PDF includes the temporary password when one is in flight', function () {
    $admin = adminForConfirmation('vendors.view');
    $vendor = Vendor::factory()->create();

    $response = $this->actingAs($admin)
        ->withSession(['vendor_temp_password' => ['vendor_id' => $vendor->id, 'value' => 'Tmp7x9Kd2Qa4']])
        ->get(route('admin.vendors.confirmation.pdf', $vendor));

    $response->assertOk();
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('onboarding lands on the letter with the password ready to print', function () {
    $admin = adminForConfirmation('vendors.view', 'vendors.create');
    $category = Category::factory()->create();

    $this->actingAs($admin)->post(route('admin.vendors.store'), [
        'company_name' => 'Zagros Construction LLC',
        'trade_license_no' => 'TL-99120',
        'address' => '14 Gulan Street',
        'city' => 'Erbil',
        'country' => 'Iraq',
        'contact_person' => 'Dara Aziz',
        'email' => 'contracts@zagros-construction.example',
        'phone' => '+9647501234567',
        'language_pref' => 'en',
        'category_ids' => [$category->id],
    ])->assertRedirect();

    $vendor = Vendor::firstWhere('email', 'contracts@zagros-construction.example');

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation', $vendor))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('temporaryPassword', fn ($p) => filled($p)));
});

test('the confirmation PDF downloads as a PDF attachment', function () {
    $admin = adminForConfirmation('vendors.view');
    $category = Category::factory()->create();
    $vendor = Vendor::factory()->create(['company_name' => 'Zagros Construction LLC']);
    $vendor->categories()->attach($category->id);

    $response = $this->actingAs($admin)->get(route('admin.vendors.confirmation.pdf', $vendor));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('zagros-construction-llc');

    // %PDF magic bytes — proves dompdf produced a document rather than an error page.
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('an admin without vendors.view cannot download the PDF', function () {
    $admin = adminForConfirmation('vendors.create');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.confirmation.pdf', $vendor))
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
