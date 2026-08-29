<?php

use App\Enums\DocumentType;
use App\Enums\VendorDocStatus;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function adminForDocuments(string ...$slugs): User
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

function pdf(string $name = 'licence.pdf'): UploadedFile
{
    // A real PDF header: the PdfFile rule sniffs content, not just extension.
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\n");
}

beforeEach(function () {
    Storage::fake('s3');
});

test('an admin files a document as verified, named as the filer', function () {
    $admin = adminForDocuments('vendors.review_docs');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.vendors.documents.store', $vendor), [
            'file' => pdf(),
            'document_type' => DocumentType::TradeLicense->value,
            'title' => 'Trade Licence 2026',
            'issue_date' => '2026-01-15',
            'expiry_date' => '2027-01-14',
        ])
        ->assertRedirect();

    $document = VendorDocument::where('vendor_id', $vendor->id)->sole();

    expect($document->status)->toBe(VendorDocStatus::Approved)
        ->and($document->uploaded_by)->toBe($admin->id)
        // Filing it IS the verification, so the same user appears as reviewer.
        ->and($document->reviewed_by)->toBe($admin->id)
        ->and($document->reviewed_at)->not->toBeNull()
        ->and($document->title)->toBe('Trade Licence 2026');

    Storage::disk('s3')->assertExists($document->file_path);
});

test('filing writes an audit row scoped to the vendor', function () {
    $admin = adminForDocuments('vendors.review_docs');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)->post(route('admin.vendors.documents.store', $vendor), [
        'file' => pdf(),
        'document_type' => DocumentType::IsoCertificate->value,
        'title' => 'ISO 9001',
    ]);

    $audit = AuditLog::where('action', 'vendor_document_filed_by_admin')->sole();

    expect($audit->user_id)->toBe($admin->id)
        ->and($audit->vendor_id)->toBe($vendor->id)
        ->and($audit->new_values['status'])->toBe('approved');
});

test('a vendor upload stays pending and names no filer', function () {
    $vendor = Vendor::factory()->create();

    $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.documents.store'), [
            'file' => pdf(),
            'document_type' => DocumentType::Insurance->value,
            'title' => 'Public liability',
        ])
        ->assertRedirect();

    $document = VendorDocument::where('vendor_id', $vendor->id)->sole();

    expect($document->status)->toBe(VendorDocStatus::Pending)
        ->and($document->uploaded_by)->toBeNull();
});

/**
 * The picker offered eight types while validation accepted six under different
 * names, so half the options a vendor could choose were rejected on submit.
 * Both sides now read App\Enums\DocumentType.
 */
test('every document type the picker offers is accepted', function (DocumentType $type) {
    $vendor = Vendor::factory()->create();

    $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.documents.store'), [
            'file' => pdf(),
            'document_type' => $type->value,
            'title' => 'Doc '.$type->value,
        ])
        ->assertSessionHasNoErrors();

    expect(VendorDocument::where('document_type', $type)->exists())->toBeTrue();
})->with(DocumentType::cases());

test('it refuses a file that is not a PDF', function () {
    $admin = adminForDocuments('vendors.review_docs');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.vendors.documents.store', $vendor), [
            'file' => UploadedFile::fake()->create('scan.jpg', 40, 'image/jpeg'),
            'document_type' => DocumentType::Other->value,
            'title' => 'Scan',
        ])
        ->assertSessionHasErrors('file');

    expect(VendorDocument::count())->toBe(0);
});

test('it refuses a user without the review permission', function () {
    $admin = adminForDocuments('vendors.view');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.vendors.documents.store', $vendor), [
            'file' => pdf(),
            'document_type' => DocumentType::Other->value,
            'title' => 'Nope',
        ])
        ->assertForbidden();

    expect(VendorDocument::count())->toBe(0);
});

test('approving a pending document records the reviewer', function () {
    $admin = adminForDocuments('vendors.review_docs');
    $vendor = Vendor::factory()->create();
    $document = VendorDocument::factory()->for($vendor)->create(['status' => VendorDocStatus::Pending]);

    $this->actingAs($admin)
        ->put(route('admin.vendors.documents.approve', [$vendor, $document]))
        ->assertRedirect();

    $document->refresh();

    expect($document->status)->toBe(VendorDocStatus::Approved)
        ->and($document->reviewed_by)->toBe($admin->id)
        // Approving someone else's upload must not claim it as filed by us.
        ->and($document->uploaded_by)->toBeNull();
});

test('rejecting requires a reason and stores it for the vendor to read', function () {
    $admin = adminForDocuments('vendors.review_docs');
    $vendor = Vendor::factory()->create();
    $document = VendorDocument::factory()->for($vendor)->create(['status' => VendorDocStatus::Pending]);

    $this->actingAs($admin)
        ->put(route('admin.vendors.documents.reject', [$vendor, $document]), [])
        ->assertSessionHasErrors('reason');

    expect($document->fresh()->status)->toBe(VendorDocStatus::Pending);

    $this->actingAs($admin)
        ->put(route('admin.vendors.documents.reject', [$vendor, $document]), [
            'reason' => 'Expired — please supply the current certificate.',
        ])
        ->assertRedirect();

    $document->refresh();

    expect($document->status)->toBe(VendorDocStatus::Rejected)
        ->and($document->review_notes)->toBe('Expired — please supply the current certificate.');
});

test('deleting removes the row and the stored file', function () {
    $admin = adminForDocuments('vendors.review_docs');
    $vendor = Vendor::factory()->create();

    $this->actingAs($admin)->post(route('admin.vendors.documents.store', $vendor), [
        'file' => pdf(),
        'document_type' => DocumentType::BankReference->value,
        'title' => 'Bank letter',
    ]);

    $document = VendorDocument::sole();
    $path = $document->file_path;

    $this->actingAs($admin)
        ->delete(route('admin.vendors.documents.destroy', [$vendor, $document]))
        ->assertRedirect();

    expect(VendorDocument::count())->toBe(0);
    Storage::disk('s3')->assertMissing($path);
});

/**
 * Vendor and document are bound independently, so without an ownership check
 * one vendor's URL would happily act on another's document while every
 * permission check passed.
 */
test('a document cannot be reached through another vendor', function (string $routeName, string $method) {
    $admin = adminForDocuments('vendors.review_docs');
    $decoy = Vendor::factory()->create();
    $owner = Vendor::factory()->create();
    $document = VendorDocument::factory()->for($owner)->create(['status' => VendorDocStatus::Pending]);

    $this->actingAs($admin)
        ->call($method, route($routeName, [$decoy, $document]), ['reason' => 'x'])
        ->assertNotFound();

    expect($document->fresh()->status)->toBe(VendorDocStatus::Pending);
})->with([
    ['admin.vendors.documents.approve', 'PUT'],
    ['admin.vendors.documents.reject', 'PUT'],
    ['admin.vendors.documents.destroy', 'DELETE'],
]);
