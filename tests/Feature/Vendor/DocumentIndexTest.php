<?php

use App\Enums\DocumentType;
use App\Enums\VendorDocStatus;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** A qualified vendor with one pending document on file. */
function documentFixture(array $attributes = []): array
{
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);

    $document = VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        ...$attributes,
    ]);

    return [$vendor, $document];
}

// ── What the page ships ──

test('the document list does not ship the S3 key', function () {
    [$vendor] = documentFixture();

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.documents.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        // The column was selected, typed, and never rendered — the bucket key
        // reached the browser for nothing.
        ->missing('documents.data.0.file_path')
        ->missing('documents.data.0.mime_type')
        ->missing('documents.data.0.reviewed_by')
    );
});

test('one vendor never sees another vendor document', function () {
    [$vendor] = documentFixture();
    VendorDocument::factory()->create();

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.documents.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('documents.data', 1));
});

// ── Expiry, which is what actually blocks a vendor from bidding ──

test('the list says which documents have expired or are about to', function () {
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);

    VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        'title' => 'Lapsed Licence',
        'status' => VendorDocStatus::Approved,
        'expiry_date' => now()->subWeek(),
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.documents.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // Nothing writes VendorDocStatus::Expired — the dashboard derives
        // expiry from expiry_date, and this page showed the date and left the
        // reader to work it out.
        ->where('documents.data.0.is_expired', true)
        ->where('documents.data.0.expires_soon', false)
    );
});

test('a document expiring within the month is flagged before it lapses', function () {
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);

    VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        'status' => VendorDocStatus::Approved,
        'expiry_date' => now()->addDays(10),
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.documents.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('documents.data.0.is_expired', false)
        ->where('documents.data.0.expires_soon', true)
    );
});

test('the page leads with the counts that need the vendor to act', function () {
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);

    VendorDocument::factory()->create(['vendor_id' => $vendor->id, 'status' => VendorDocStatus::Pending]);
    VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        'status' => VendorDocStatus::Approved,
        'expiry_date' => now()->subDay(),
    ]);
    VendorDocument::factory()->create(['vendor_id' => $vendor->id, 'status' => VendorDocStatus::Rejected]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.documents.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('summary.total', 3)
        ->where('summary.awaiting_review', 1)
        ->where('summary.needs_attention', 2)
        ->has('summary.valid')
        ->has('statusCounts')
        ->has('statusOptions')
    );
});

// ── Filtering, search and sorting ──

test('the list can be filtered to what needs attention', function () {
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);

    VendorDocument::factory()->create(['vendor_id' => $vendor->id, 'status' => VendorDocStatus::Pending]);
    VendorDocument::factory()->create([
        'vendor_id' => $vendor->id,
        'title' => 'Lapsed Licence',
        'status' => VendorDocStatus::Approved,
        'expiry_date' => now()->subDay(),
    ]);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.documents.index', ['filter' => 'needs_attention']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.title', 'Lapsed Licence')
    );
});

test('the list can be searched by title', function () {
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    VendorDocument::factory()->create(['vendor_id' => $vendor->id, 'title' => 'Trade Licence 2026']);
    VendorDocument::factory()->create(['vendor_id' => $vendor->id, 'title' => 'ISO 9001']);

    $response = $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.documents.index', ['search' => 'Licence']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.title', 'Trade Licence 2026')
    );
});

test('an unknown sort column falls back instead of erroring', function () {
    [$vendor] = documentFixture();

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.documents.index', ['sort' => 'file_path', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.sort', 'created_at'));
});

// ── Retrieving the file ──

test('a vendor can download a document they uploaded', function () {
    Storage::fake('s3');

    [$vendor, $document] = documentFixture([
        'file_path' => 'vendors/docs/licence.pdf',
        'title' => 'Trade Licence 2026',
    ]);
    Storage::disk('s3')->put('vendors/docs/licence.pdf', '%PDF-1.4 fake');

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.documents.download', $document))
        ->assertOk()
        ->assertDownload('Trade Licence 2026.pdf');
});

test('a vendor cannot download another vendor document', function () {
    Storage::fake('s3');

    [$vendor] = documentFixture();
    $rivalDocument = VendorDocument::factory()->create();

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.documents.download', $rivalDocument))
        ->assertNotFound();
});

test('downloading a document is written to the access log', function () {
    Storage::fake('s3');

    [$vendor, $document] = documentFixture(['file_path' => 'vendors/docs/licence.pdf']);
    Storage::disk('s3')->put('vendors/docs/licence.pdf', '%PDF-1.4 fake');

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.documents.download', $document))
        ->assertOk();

    $this->assertDatabaseHas('document_access_logs', [
        'vendor_id' => $vendor->id,
        'document_type' => VendorDocument::class,
        'document_id' => $document->id,
        'action' => 'downloaded',
    ]);
});

// ── Deleting ──

test('deleting another vendor document is not found rather than forbidden', function () {
    [$vendor] = documentFixture();
    $rivalDocument = VendorDocument::factory()->create();

    // 403 confirms the row exists at that id. Whose it is, and whether it is
    // anyone's, are both none of this vendor's business.
    $this->actingAs($vendor, 'vendor')
        ->delete(route('vendor.documents.destroy', $rivalDocument))
        ->assertNotFound();

    $this->assertDatabaseHas('vendor_documents', ['id' => $rivalDocument->id]);
});

test('a reviewed document cannot be deleted', function () {
    [$vendor, $document] = documentFixture(['status' => VendorDocStatus::Approved]);

    $this->actingAs($vendor, 'vendor')
        ->delete(route('vendor.documents.destroy', $document));

    $this->assertDatabaseHas('vendor_documents', ['id' => $document->id]);
});

// ── Enum plumbing ──

test('the document status vocabulary is complete in all three locales', function () {
    $keys = array_map(fn (VendorDocStatus $case) => $case->labelKey(), VendorDocStatus::cases());
    $keys[] = 'vendor.doc_expires_soon';
    $keys[] = 'vendor.doc_expired';

    foreach (array_merge($keys, array_map(fn (DocumentType $c) => $c->labelKey(), DocumentType::cases())) as $key) {
        foreach (['en', 'ar', 'ku'] as $locale) {
            $catalogue = json_decode(file_get_contents(lang_path("{$locale}.json")), true);

            expect(array_key_exists($key, $catalogue))
                ->toBeTrue("{$key} missing from {$locale}.json");
        }
    }
});
