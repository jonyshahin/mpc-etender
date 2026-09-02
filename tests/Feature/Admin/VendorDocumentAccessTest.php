<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategoryRequest;
use App\Models\VendorCategoryRequestEvidence;
use App\Models\VendorDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function adminForAccess(string ...$slugs): User
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

beforeEach(function () {
    Storage::fake('s3');
});

// ── What the vendor detail page hands out ──

test('the vendor page does not ship bucket keys or pre-signed urls', function () {
    $admin = adminForAccess('vendors.view');
    $vendor = Vendor::factory()->create();
    VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        'file_path' => 'vendors/docs/licence.pdf',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.vendors.show', $vendor));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // file_path was selected, typed on the React side and never rendered.
        ->missing('vendor.documents.0.file_path')
        // Worse than the key itself: the controller minted a temporary S3 URL
        // for every document on every page load, whether or not anyone clicked
        // one, and nothing recorded that it had done so.
        ->missing('documentUrls')
    );
});

test('an admin downloads a vendor document through the app', function () {
    $admin = adminForAccess('vendors.view');
    $vendor = Vendor::factory()->create();
    $document = VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        'file_path' => 'vendors/docs/licence.pdf',
        'title' => 'Trade Licence 2026',
    ]);
    Storage::disk('s3')->put('vendors/docs/licence.pdf', '%PDF-1.4 fake');

    $this->actingAs($admin)
        ->get(route('admin.vendors.documents.download', [$vendor, $document]))
        ->assertOk()
        ->assertDownload('Trade Licence 2026.pdf');
});

test('an admin document download is written to the access log', function () {
    $admin = adminForAccess('vendors.view');
    $vendor = Vendor::factory()->create();
    $document = VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        'file_path' => 'vendors/docs/licence.pdf',
    ]);
    Storage::disk('s3')->put('vendors/docs/licence.pdf', '%PDF-1.4 fake');

    $this->actingAs($admin)
        ->get(route('admin.vendors.documents.download', [$vendor, $document]))
        ->assertOk();

    // CLAUDE.md requires document access logged, and the log records the user
    // for staff access the way it records the vendor for vendor access.
    $this->assertDatabaseHas('document_access_logs', [
        'user_id' => $admin->id,
        'vendor_id' => $vendor->id,
        'document_type' => VendorDocument::class,
        'document_id' => $document->id,
        'action' => 'downloaded',
    ]);
});

test('a document from another vendor is not reachable through this one', function () {
    $admin = adminForAccess('vendors.view');
    $vendor = Vendor::factory()->create();
    $otherDocument = VendorDocument::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.vendors.documents.download', [$vendor, $otherDocument]))
        ->assertNotFound();
});

// ── The permission the page never checked ──

test('an admin without vendors.view cannot open the vendor page', function () {
    $admin = adminForAccess('vendors.update');
    $vendor = Vendor::factory()->create();

    // show() was the only read method on this controller that did not
    // authorize; confirmation(), which discloses strictly less, always has.
    $this->actingAs($admin)
        ->get(route('admin.vendors.show', $vendor))
        ->assertForbidden();
});

test('an admin without vendors.view cannot download a vendor document', function () {
    $admin = adminForAccess('vendors.update');
    $vendor = Vendor::factory()->create();
    $document = VendorDocument::factory()->create(['vendor_id' => $vendor->id]);

    $this->actingAs($admin)
        ->get(route('admin.vendors.documents.download', [$vendor, $document]))
        ->assertForbidden();
});

// ── Category request evidence ──

test('evidence is streamed to the reviewer rather than handed out as a bucket url', function () {
    $admin = adminForAccess('vendors.review_category_requests');
    $vendor = Vendor::factory()->create();
    $request = VendorCategoryRequest::query()->create([
        'vendor_id' => $vendor->id,
        'justification' => 'Taking on a mechanical division.',
        'status' => 'pending',
    ]);
    $evidence = VendorCategoryRequestEvidence::query()->create([
        'request_id' => $request->id,
        'uploaded_by_vendor_id' => $vendor->id,
        'original_name' => 'iso-certificate.pdf',
        'mime_type' => 'application/pdf',
        'size' => 12_345,
        'path' => 'vendor-evidence/iso.pdf',
    ]);
    Storage::disk('s3')->put('vendor-evidence/iso.pdf', '%PDF-1.4 fake');

    $this->actingAs($admin)
        ->get(route('admin.vendor-category-requests.evidence.download', $evidence))
        ->assertOk()
        ->assertDownload('iso-certificate.pdf');

    $this->assertDatabaseHas('document_access_logs', [
        'user_id' => $admin->id,
        'vendor_id' => $vendor->id,
        'document_type' => VendorCategoryRequestEvidence::class,
        'document_id' => $evidence->id,
        'action' => 'downloaded',
    ]);
});
