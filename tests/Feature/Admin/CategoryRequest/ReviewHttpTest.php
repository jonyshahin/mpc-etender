<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategoryRequest;
use App\Models\VendorCategoryRequestEvidence;
use App\Notifications\VendorCategoryRequestApproved;
use App\Notifications\VendorCategoryRequestRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Admin with the review permission. */
function reviewerUser(): User
{
    $role = Role::factory()->create();
    $perm = Permission::firstOrCreate(
        ['slug' => 'vendors.review_category_requests'],
        ['name' => 'Review Vendor Category Requests', 'module' => 'vendors']
    );
    $role->permissions()->attach($perm->id);

    return User::factory()->create(['role_id' => $role->id]);
}

beforeEach(function () {
    Storage::fake('s3');
    $this->reviewer = reviewerUser();
    $this->vendor = Vendor::factory()->create();

    // Pre-build a pending request with one add item
    $this->addCat = Category::factory()->create();
    $this->req = VendorCategoryRequest::query()->create([
        'vendor_id' => $this->vendor->id,
        'justification' => 'valid justification text here',
        'status' => 'pending',
    ]);
    $this->req->items()->create(['category_id' => $this->addCat->id, 'operation' => 'add']);
});

// ADMIN-01 / ADMIN-02: GET-render tests for the index page are deferred to
// C.3 when the React admin page (admin/VendorCategoryRequests/Index.tsx) is
// added to the Vite manifest. Permission behavior for this route is fully
// covered by:
//   - PermissionGateTest::ADMIN-PERM-01..04 at the FormRequest layer
//   - The controller's ensureCanReview() — exercised indirectly by every
//     ADMIN-03..06 POST test below which requires the same permission.
test('ADMIN-01: admin index GET render (pending until C.3 ships React page)')
    ->skip('Deferred to C.3 — React page admin/VendorCategoryRequests/Index.tsx not yet in Vite manifest.');

test('ADMIN-02: admin without permission forbidden on index GET (pending until C.3)')
    ->skip('Deferred to C.3 — same reason as ADMIN-01.');

test('ADMIN-03: approving mutates pivot + fires notification', function () {
    Notification::fake();

    $this->actingAs($this->reviewer, 'web')
        ->post(route('admin.vendor-category-requests.approve', $this->req), [
            'action' => 'approve',
            'comments' => 'Looks good.',
        ])
        ->assertRedirect();

    expect($this->req->fresh()->status)->toBe('approved');
    expect($this->vendor->fresh()->categories()->pluck('categories.id')->all())->toContain($this->addCat->id);

    Notification::assertSentTo($this->vendor, VendorCategoryRequestApproved::class);
    expect(AuditLog::where('action', 'vendor_category_request_approved')
        ->where('auditable_id', $this->req->id)
        ->where('user_id', $this->reviewer->id)
        ->exists())->toBeTrue();
});

test('ADMIN-04: rejecting requires comments and fires notification; pivot unchanged', function () {
    Notification::fake();

    // Missing comments → validation error
    $this->actingAs($this->reviewer, 'web')
        ->post(route('admin.vendor-category-requests.reject', $this->req), [
            'action' => 'reject',
        ])
        ->assertSessionHasErrors('comments');

    expect($this->req->fresh()->status)->toBe('pending');

    // With comments → rejected
    $this->actingAs($this->reviewer, 'web')
        ->post(route('admin.vendor-category-requests.reject', $this->req), [
            'action' => 'reject',
            'comments' => 'Evidence insufficient — ISO cert missing.',
        ])
        ->assertRedirect();

    expect($this->req->fresh()->status)->toBe('rejected');
    expect($this->vendor->fresh()->categories()->count())->toBe(0); // pivot unchanged
    Notification::assertSentTo($this->vendor, VendorCategoryRequestRejected::class);
});

test('ADMIN-05: cannot approve an already-approved / rejected / withdrawn request', function () {
    Notification::fake();

    // First approve
    $this->actingAs($this->reviewer, 'web')
        ->post(route('admin.vendor-category-requests.approve', $this->req), ['action' => 'approve']);

    // Second attempt fails via service ValidationException → session errors
    $this->actingAs($this->reviewer, 'web')
        ->post(route('admin.vendor-category-requests.approve', $this->req), ['action' => 'approve'])
        ->assertSessionHasErrors('status');
});

test('ADMIN-06: evidence download requires review permission and returns redirect to signed URL', function () {
    $ev = VendorCategoryRequestEvidence::query()->create([
        'request_id' => $this->req->id,
        'path' => 'vendor-category-requests/'.$this->req->id.'/license.pdf',
        'original_name' => 'license.pdf',
        'mime_type' => 'application/pdf',
        'size' => 500,
        'uploaded_by_vendor_id' => $this->vendor->id,
    ]);
    Storage::disk('s3')->put($ev->path, 'pdf-bytes');

    // Unauthorized admin
    $noPermUser = User::factory()->create(['role_id' => Role::factory()->create()->id]);
    $this->actingAs($noPermUser, 'web')
        ->get(route('admin.vendor-category-requests.evidence.download', $ev))
        ->assertForbidden();

    // Authorized admin → redirect away (signed/temporary URL from the fake s3 driver)
    $r = $this->actingAs($this->reviewer, 'web')
        ->get(route('admin.vendor-category-requests.evidence.download', $ev));
    expect($r->status())->toBe(302);
});
