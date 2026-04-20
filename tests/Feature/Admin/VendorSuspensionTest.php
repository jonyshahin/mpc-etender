<?php

use App\Enums\VendorStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAdminWithPermission(string $slug): User
{
    $role = Role::factory()->create();
    $permission = Permission::create([
        'name' => ucwords(str_replace('.', ' ', $slug)),
        'slug' => $slug,
        'module' => explode('.', $slug)[0],
    ]);
    $role->permissions()->attach($permission->id);

    return User::factory()->create(['role_id' => $role->id]);
}

test('admin can suspend an approved vendor with a reason', function () {
    $admin = createAdminWithPermission('vendors.qualify');
    $vendor = Vendor::factory()->qualified()->create();

    $response = $this->actingAs($admin)
        ->put(route('admin.vendors.suspend', $vendor), [
            'reason' => 'Documents expired — pending re-verification',
        ]);

    $response->assertRedirect();

    $vendor->refresh();
    expect($vendor->prequalification_status)->toBe(VendorStatus::Suspended);
    expect($vendor->rejection_reason)->toBe('Documents expired — pending re-verification');
    expect($vendor->is_active)->toBeFalse();
});

test('suspend requires a reason', function () {
    $admin = createAdminWithPermission('vendors.qualify');
    $vendor = Vendor::factory()->qualified()->create();

    $response = $this->actingAs($admin)
        ->put(route('admin.vendors.suspend', $vendor), [
            'reason' => '',
        ]);

    $response->assertSessionHasErrors('reason');

    $vendor->refresh();
    expect($vendor->prequalification_status)->toBe(VendorStatus::Qualified);
});

test('user without permission cannot suspend a vendor', function () {
    $role = Role::factory()->create();
    $user = User::factory()->create(['role_id' => $role->id]);
    $vendor = Vendor::factory()->qualified()->create();

    $response = $this->actingAs($user)
        ->put(route('admin.vendors.suspend', $vendor), [
            'reason' => 'Test suspension',
        ]);

    $response->assertForbidden();

    $vendor->refresh();
    expect($vendor->prequalification_status)->toBe(VendorStatus::Qualified);
});

test('suspend creates an audit log entry', function () {
    $admin = createAdminWithPermission('vendors.qualify');
    $vendor = Vendor::factory()->qualified()->create();

    $this->actingAs($admin)
        ->put(route('admin.vendors.suspend', $vendor), [
            'reason' => 'Compliance issue',
        ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $admin->id,
        'auditable_type' => Vendor::class,
        'auditable_id' => $vendor->id,
        'action' => 'updated',
    ]);
});
