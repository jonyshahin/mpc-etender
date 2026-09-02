<?php

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategoryRequest;
use App\Models\VendorCategoryRequestEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** @return array{0: Vendor, 1: VendorCategoryRequest} */
function categoryRequestFixture(array $attributes = []): array
{
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);

    $request = VendorCategoryRequest::query()->create([
        'vendor_id' => $vendor->id,
        'justification' => 'We have taken on a mechanical division and hold the certificates.',
        'status' => 'pending',
        ...$attributes,
    ]);

    return [$vendor, $request];
}

// ── What the list ships ──

test('the request list does not ship the reviewing MPC user id', function () {
    $reviewer = User::factory()->create();
    [$vendor] = categoryRequestFixture([
        'status' => 'rejected',
        'reviewed_by' => $reviewer->id,
        'reviewer_comments' => 'Certificates were out of date.',
        'reviewed_at' => now(),
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.category-requests.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // The list passed whole models, so the raw foreign key to the internal
        // user who reviewed it went over the wire. The detail page shows that
        // person's name deliberately; the list showed nothing and sent an id.
        ->has('requests.data', 1)
        ->missing('requests.data.0.reviewed_by')
    );
});

test('one vendor never sees another vendor request', function () {
    [$vendor] = categoryRequestFixture();
    categoryRequestFixture();

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.category-requests.index'))
        ->assertInertia(fn ($page) => $page->has('requests.data', 1));
});

// ── Headline figures and filtering ──

test('the list leads with counts and can be filtered by status', function () {
    [$vendor] = categoryRequestFixture();
    VendorCategoryRequest::query()->create([
        'vendor_id' => $vendor->id,
        'justification' => 'An older request that was approved.',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.category-requests.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('summary.total', 2)
        ->where('summary.open', 1)
        ->where('summary.approved', 1)
        ->has('statusOptions')
        ->has('filters.status')
    );

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.category-requests.index', ['status' => 'approved']))
        ->assertInertia(fn ($page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.status', 'approved')
        );
});

test('an unknown status filter falls back instead of returning nothing', function () {
    [$vendor] = categoryRequestFixture();

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.category-requests.index', ['status' => 'reviewed_by']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('requests.data', 1)
            ->where('filters.status', null)
        );
});

// ── Evidence downloads ──

test('downloading evidence is written to the access log', function () {
    Storage::fake('s3');

    [$vendor, $request] = categoryRequestFixture();
    $evidence = VendorCategoryRequestEvidence::query()->create([
        'request_id' => $request->id,
        'uploaded_by_vendor_id' => $vendor->id,
        'original_name' => 'iso-certificate.pdf',
        'mime_type' => 'application/pdf',
        'size' => 12_345,
        'path' => 'vendor-evidence/iso.pdf',
    ]);
    Storage::disk('s3')->put('vendor-evidence/iso.pdf', '%PDF-1.4 fake');

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.category-requests.evidence.download', [$request, $evidence]))
        ->assertOk();

    // CLAUDE.md requires document access to be logged, and FileUploadService
    // already carries the helper. This path redirected to a signed bucket URL
    // and recorded nothing.
    $this->assertDatabaseHas('document_access_logs', [
        'vendor_id' => $vendor->id,
        'document_type' => VendorCategoryRequestEvidence::class,
        'document_id' => $evidence->id,
        'action' => 'downloaded',
    ]);
});

test('evidence is streamed rather than handed out as a bucket url', function () {
    Storage::fake('s3');

    [$vendor, $request] = categoryRequestFixture();
    $evidence = VendorCategoryRequestEvidence::query()->create([
        'request_id' => $request->id,
        'uploaded_by_vendor_id' => $vendor->id,
        'original_name' => 'iso-certificate.pdf',
        'mime_type' => 'application/pdf',
        'size' => 12_345,
        'path' => 'vendor-evidence/iso.pdf',
    ]);
    Storage::disk('s3')->put('vendor-evidence/iso.pdf', '%PDF-1.4 fake');

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.category-requests.evidence.download', [$request, $evidence]))
        ->assertOk()
        ->assertDownload('iso-certificate.pdf');
});

test('a vendor cannot download evidence from another vendor request', function () {
    Storage::fake('s3');

    [$vendor] = categoryRequestFixture();
    [, $rivalRequest] = categoryRequestFixture();
    $evidence = VendorCategoryRequestEvidence::query()->create([
        'request_id' => $rivalRequest->id,
        'uploaded_by_vendor_id' => $rivalRequest->vendor_id,
        'original_name' => 'private.pdf',
        'mime_type' => 'application/pdf',
        'size' => 10,
        'path' => 'vendor-evidence/private.pdf',
    ]);

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.category-requests.evidence.download', [$rivalRequest, $evidence]))
        ->assertForbidden();
});
