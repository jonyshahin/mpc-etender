<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function adminForUpdate(string ...$slugs): User
{
    $role = Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);

    foreach ($slugs as $slug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace('.', ' ', $slug)), 'module' => explode('.', $slug)[0]],
        );
        $role->permissions()->attach($permission->id);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

/** @return array<string, mixed> */
function vendorUpdatePayload(Vendor $vendor, array $overrides = []): array
{
    return array_merge([
        'company_name' => $vendor->company_name,
        'company_name_ar' => $vendor->company_name_ar,
        'trade_license_no' => $vendor->trade_license_no,
        'address' => $vendor->address,
        'city' => $vendor->city,
        'country' => $vendor->country,
        'website' => $vendor->website,
        'contact_person' => $vendor->contact_person,
        'email' => $vendor->email,
        'phone' => $vendor->phone,
        'whatsapp_number' => $vendor->whatsapp_number,
        'category_ids' => $vendor->categories->pluck('id')->all(),
    ], $overrides);
}

function vendorForUpdate(array $attributes = []): Vendor
{
    $vendor = Vendor::factory()->create($attributes);
    $vendor->categories()->attach(Category::factory()->create()->id);

    return $vendor->load('categories');
}

test('an admin corrects a vendor\'s details', function () {
    $admin = adminForUpdate('vendors.view', 'vendors.update');
    $vendor = vendorForUpdate(['company_name' => 'Sama cool', 'city' => 'Erbil']);

    $this->actingAs($admin)
        ->put(route('admin.vendors.update', $vendor), vendorUpdatePayload($vendor, [
            'company_name' => 'Sama Cool HVAC',
            'city' => 'Mosul',
            'contact_person' => 'ابراهيم سمير يونس',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($vendor->fresh())
        ->company_name->toBe('Sama Cool HVAC')
        ->city->toBe('Mosul')
        ->contact_person->toBe('ابراهيم سمير يونس');
});

test('it records only the fields that actually moved', function () {
    $admin = adminForUpdate('vendors.view', 'vendors.update');
    $vendor = vendorForUpdate(['company_name' => 'Sama cool', 'city' => 'Erbil']);

    $this->actingAs($admin)->put(
        route('admin.vendors.update', $vendor),
        vendorUpdatePayload($vendor, ['city' => 'Mosul']),
    );

    $audit = AuditLog::where('action', 'vendor_updated_by_admin')->sole();

    expect($audit->user_id)->toBe($admin->id)
        ->and($audit->vendor_id)->toBe($vendor->id)
        ->and($audit->old_values)->toBe(['city' => 'Erbil'])
        ->and($audit->new_values)->toBe(['city' => 'Mosul']);
});

test('saving an unchanged form writes no audit row', function () {
    $admin = adminForUpdate('vendors.view', 'vendors.update');
    $vendor = vendorForUpdate();

    $this->actingAs($admin)
        ->put(route('admin.vendors.update', $vendor), vendorUpdatePayload($vendor))
        ->assertSessionHasNoErrors();

    // An audit trail full of no-op saves buries the changes that matter.
    expect(AuditLog::where('action', 'vendor_updated_by_admin')->count())->toBe(0);
});

test('it replaces the category assignment rather than adding to it', function () {
    $admin = adminForUpdate('vendors.view', 'vendors.update');
    $vendor = vendorForUpdate();
    $replacement = Category::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.vendors.update', $vendor), vendorUpdatePayload($vendor, [
            'category_ids' => [$replacement->id],
        ]))
        ->assertSessionHasNoErrors();

    expect($vendor->fresh()->categories->pluck('id')->all())->toBe([$replacement->id]);

    $audit = AuditLog::where('action', 'vendor_updated_by_admin')->sole();
    expect($audit->new_values)->toHaveKey('category_ids');
});

/**
 * The email is the vendor's login identity, so the uniqueness rule has to
 * ignore their own row — otherwise saving a form nobody touched fails against
 * the record it is saving.
 */
test('it accepts the vendor\'s own email unchanged but rejects another vendor\'s', function () {
    $admin = adminForUpdate('vendors.view', 'vendors.update');
    $vendor = vendorForUpdate(['email' => 'first@example.test']);
    $other = Vendor::factory()->create(['email' => 'second@example.test']);

    $this->actingAs($admin)
        ->put(route('admin.vendors.update', $vendor), vendorUpdatePayload($vendor))
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->put(route('admin.vendors.update', $vendor), vendorUpdatePayload($vendor, [
            'email' => $other->email,
        ]))
        ->assertSessionHasErrors('email');

    expect($vendor->fresh()->email)->toBe('first@example.test');
});

test('it refuses an admin without the update permission', function () {
    $admin = adminForUpdate('vendors.view');
    $vendor = vendorForUpdate(['company_name' => 'Sama cool']);

    $this->actingAs($admin)
        ->put(route('admin.vendors.update', $vendor), vendorUpdatePayload($vendor, [
            'company_name' => 'Hijacked',
        ]))
        ->assertForbidden();

    expect($vendor->fresh()->company_name)->toBe('Sama cool');
});

test('it leaves credentials and standing alone', function () {
    $admin = adminForUpdate('vendors.view', 'vendors.update');
    $vendor = vendorForUpdate([
        'prequalification_status' => 'qualified',
        'is_active' => true,
    ]);
    $passwordBefore = $vendor->password;

    $this->actingAs($admin)->put(route('admin.vendors.update', $vendor), vendorUpdatePayload($vendor, [
        'company_name' => 'Renamed',
        // Neither is on the form; both must be ignored if posted anyway.
        'password' => 'attacker-chosen',
        'prequalification_status' => 'rejected',
        'is_active' => false,
    ]));

    $after = $vendor->fresh();

    expect($after->company_name)->toBe('Renamed')
        ->and($after->password)->toBe($passwordBefore)
        ->and($after->prequalification_status->value)->toBe('qualified')
        ->and($after->is_active)->toBeTrue();
});

test('a required field cannot be blanked out', function () {
    $admin = adminForUpdate('vendors.view', 'vendors.update');
    $vendor = vendorForUpdate();

    $this->actingAs($admin)
        ->put(route('admin.vendors.update', $vendor), vendorUpdatePayload($vendor, [
            'company_name' => '',
            'category_ids' => [],
        ]))
        ->assertSessionHasErrors(['company_name', 'category_ids']);
});

test('the detail page carries what the edit form needs', function () {
    $admin = adminForUpdate('vendors.view', 'vendors.update');
    $vendor = vendorForUpdate();

    $this->actingAs($admin)
        ->get(route('admin.vendors.show', $vendor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canUpdate', true)
            ->has('categories')
            ->where('vendorCategoryIds', $vendor->categories->pluck('id')->all())
        );
});
