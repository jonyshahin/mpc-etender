<?php

use App\Enums\VendorStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Admin-initiated vendor onboarding — the only path that creates a vendor now
 * that public self-registration has been removed.
 *
 * Note: helper names in Pest files are global, so this cannot reuse
 * createAdminWithPermission() from VendorSuspensionTest.
 */
function adminWithVendorPermissions(string ...$slugs): User
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

function vendorPayload(array $overrides = []): array
{
    $payload = [
        'company_name' => 'Zagros Construction LLC',
        'company_name_ar' => 'شركة زاغروس للإنشاءات',
        'trade_license_no' => 'TL-99120',
        'address' => '14 Gulan Street, Ankawa',
        'city' => 'Erbil',
        'country' => 'Iraq',
        'website' => 'https://zagros-construction.example',
        'contact_person' => 'Dara Aziz',
        'email' => 'contracts@zagros-construction.example',
        'phone' => '+9647501234567',
        'whatsapp_number' => '+9647501234567',
        'language_pref' => 'en',
    ];

    // Only mint a Category when the caller didn't supply one — building it
    // unconditionally would leave a stray row behind on every call and skew
    // any test that counts categories.
    if (! array_key_exists('category_ids', $overrides)) {
        $payload['category_ids'] = [Category::factory()->create()->id];
    }

    return array_merge($payload, $overrides);
}

test('admin with vendors.create can onboard a vendor', function () {
    $admin = adminWithVendorPermissions('vendors.create');
    $category = Category::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.vendors.store'), vendorPayload([
            'category_ids' => [$category->id],
        ]));

    $vendor = Vendor::where('email', 'contracts@zagros-construction.example')->first();

    expect($vendor)->not->toBeNull();
    // Lands on the confirmation letter, which is what the admin hands over — it
    // is the only moment the one-time password can appear on it.
    $response->assertRedirect(route('admin.vendors.confirmation', $vendor));

    expect($vendor->company_name)->toBe('Zagros Construction LLC');
    expect($vendor->prequalification_status)->toBe(VendorStatus::Pending);
    expect($vendor->is_active)->toBeTrue();
    expect($vendor->categories->pluck('id')->all())->toBe([$category->id]);
});

test('category pivot rows get a populated UUID primary key', function () {
    $admin = adminWithVendorPermissions('vendors.create');
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.vendors.store'), vendorPayload([
            'category_ids' => [$category->id],
        ]));

    $vendor = Vendor::firstWhere('email', 'contracts@zagros-construction.example');

    // vendor_categories has a UUID primary key, and Laravel's attach() builds a
    // raw INSERT that bypasses the pivot model's HasUuids creating event. SQLite
    // tolerates a NULL PK; MySQL rejects it — so assert the value explicitly
    // rather than relying on the driver to complain.
    $pivotId = DB::table('vendor_categories')
        ->where('vendor_id', $vendor->id)
        ->where('category_id', $category->id)
        ->value('id');

    expect($pivotId)->not->toBeNull();
});

test('onboarded vendor must change the generated password on first login', function () {
    $admin = adminWithVendorPermissions('vendors.create');

    $this->actingAs($admin)
        ->post(route('admin.vendors.store'), vendorPayload());

    $vendor = Vendor::firstWhere('email', 'contracts@zagros-construction.example');

    expect($vendor->must_change_password)->toBeTrue();

    // Surfaced to the admin exactly once, via a flash scoped to this vendor so it
    // cannot be printed on a different vendor's letter.
    $flash = session('vendor_temp_password');
    expect($flash['vendor_id'])->toBe($vendor->id);

    $temporaryPassword = $flash['value'];
    expect($temporaryPassword)->toBeString()->not->toBeEmpty();
    expect(Hash::check($temporaryPassword, $vendor->password))->toBeTrue();
});

test('onboarding writes an audit row naming the responsible admin', function () {
    $admin = adminWithVendorPermissions('vendors.create');

    $this->actingAs($admin)
        ->post(route('admin.vendors.store'), vendorPayload());

    $vendor = Vendor::firstWhere('email', 'contracts@zagros-construction.example');

    $log = AuditLog::where('auditable_type', Vendor::class)
        ->where('auditable_id', $vendor->id)
        ->where('action', 'vendor_created_by_admin')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($admin->id);
});

test('admin without vendors.create cannot onboard a vendor', function () {
    // Has vendor access, but not the create permission.
    $admin = adminWithVendorPermissions('vendors.view');

    $this->actingAs($admin)
        ->post(route('admin.vendors.store'), vendorPayload())
        ->assertForbidden();

    expect(Vendor::count())->toBe(0);
});

test('vendor email must be unique', function () {
    $admin = adminWithVendorPermissions('vendors.create');
    Vendor::factory()->create(['email' => 'contracts@zagros-construction.example']);

    $this->actingAs($admin)
        ->post(route('admin.vendors.store'), vendorPayload())
        ->assertSessionHasErrors('email');

    expect(Vendor::count())->toBe(1);
});

test('vendor list offers the create affordance only to permitted admins', function () {
    Category::factory()->create();

    $this->actingAs(adminWithVendorPermissions('vendors.view', 'vendors.create'))
        ->get(route('admin.vendors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Vendors/Index')
            ->where('canCreate', true)
            // The dialog cannot render a category picker without these.
            ->has('categories', 1)
        );
});

test('vendor list hides the create affordance without vendors.create', function () {
    $this->actingAs(adminWithVendorPermissions('vendors.view'))
        ->get(route('admin.vendors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', false));
});

test('duplicate category ids are rejected rather than violating the pivot unique index', function () {
    $admin = adminWithVendorPermissions('vendors.create');
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.vendors.store'), vendorPayload([
            'category_ids' => [$category->id, $category->id],
        ]))
        ->assertSessionHasErrors('category_ids.0');

    expect(Vendor::count())->toBe(0);
});

test('kurdish is accepted as a language preference', function () {
    $admin = adminWithVendorPermissions('vendors.create');

    $this->actingAs($admin)
        ->post(route('admin.vendors.store'), vendorPayload(['language_pref' => 'ku']))
        ->assertSessionHasNoErrors();

    expect(Vendor::firstWhere('email', 'contracts@zagros-construction.example')->language_pref)
        ->toBe('ku');
});

test('an explicitly null language preference is rejected, not written as NULL', function () {
    $admin = adminWithVendorPermissions('vendors.create');

    // vendors.language_pref is NOT NULL DEFAULT 'ar'. Laravel's
    // ConvertEmptyStringsToNull turns '' into null, so without a `required`
    // rule this would reach the insert and 500 on MySQL (SQLite would accept it).
    $this->actingAs($admin)
        ->post(route('admin.vendors.store'), vendorPayload(['language_pref' => '']))
        ->assertSessionHasErrors('language_pref');

    expect(Vendor::count())->toBe(0);
});

test('at least one category is required', function () {
    $admin = adminWithVendorPermissions('vendors.create');

    $this->actingAs($admin)
        ->post(route('admin.vendors.store'), vendorPayload(['category_ids' => []]))
        ->assertSessionHasErrors('category_ids');

    expect(Vendor::count())->toBe(0);
});
