<?php

use App\Models\Category;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use App\Rules\PdfFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

/**
 * 5121 KB = 1 KB over the 5120 cap. Picking the smallest oversize file
 * keeps the assertion focused on the boundary, not on whether 50 MB
 * fakes work in the test runner.
 */
function policyOversizePdf(): UploadedFile
{
    return UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf');
}

function policyDocxFile(): UploadedFile
{
    return UploadedFile::fake()->create(
        'evil.docx',
        100,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    );
}

function policyValidPdf(string $name = 'spec.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 200, 'application/pdf');
}

function policyMpcUser(array $permissionSlugs): User
{
    $role = Role::factory()->create();
    foreach ($permissionSlugs as $slug) {
        $perm = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace('.', ' ', $slug)), 'module' => explode('.', $slug)[0]],
        );
        $role->permissions()->attach($perm->id);
    }
    $user = User::factory()->create(['role_id' => $role->id]);
    Project::factory()->create(['created_by' => $user->id]);

    return $user;
}

// ── PdfFile rule (unit) ─────────────────────────────────────────────

test('PdfFile rule accepts a valid PDF under 5 MB', function () {
    $validator = Validator::make(
        ['file' => policyValidPdf()],
        ['file' => [new PdfFile]],
    );

    expect($validator->fails())->toBeFalse();
});

test('PdfFile rule rejects non-PDF mime types', function () {
    $validator = Validator::make(
        ['file' => policyDocxFile()],
        ['file' => [new PdfFile]],
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('file'))->toContain('PDF');
});

test('PdfFile rule rejects PDFs over 5 MB', function () {
    $validator = Validator::make(
        ['file' => policyOversizePdf()],
        ['file' => [new PdfFile]],
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('file'))->toContain('5 MB');
});

// ── Vendor\FileUploadRequest (vendor profile docs) ──────────────────

test('Vendor profile docs reject non-PDF', function () {
    $vendor = Vendor::factory()->create();

    $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.documents.store'), [
            'file' => policyDocxFile(),
            'document_type' => 'trade_license',
            'title' => 'Trade License',
        ])
        ->assertSessionHasErrors('file');
});

test('Vendor profile docs reject oversize PDF', function () {
    $vendor = Vendor::factory()->create();

    $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.documents.store'), [
            'file' => policyOversizePdf(),
            'document_type' => 'trade_license',
            'title' => 'Trade License',
        ])
        ->assertSessionHasErrors('file');
});

// ── Vendor\StoreCategoryChangeRequest (evidence array) ──────────────

test('Category-change evidence rejects non-PDF', function () {
    $vendor = Vendor::factory()->create();
    $cat = Category::factory()->create();

    $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.category-requests.store'), [
            'justification' => 'We have new equipment that should qualify us for additional categories.',
            'add_categories' => [$cat->id],
            'evidence' => [policyDocxFile()],
        ])
        ->assertSessionHasErrors('evidence.0');
});

test('Category-change evidence rejects oversize PDF', function () {
    $vendor = Vendor::factory()->create();
    $cat = Category::factory()->create();

    $this->actingAs($vendor, 'vendor')
        ->post(route('vendor.category-requests.store'), [
            'justification' => 'We have new equipment that should qualify us for additional categories.',
            'add_categories' => [$cat->id],
            'evidence' => [policyOversizePdf()],
        ])
        ->assertSessionHasErrors('evidence.0');
});

// ── Tender\StoreTenderDocumentRequest (admin tender docs) ───────────

test('Admin tender doc upload rejects non-PDF', function () {
    $user = policyMpcUser(['tenders.update']);
    $tender = Tender::factory()->create(['status' => 'draft']);

    $this->actingAs($user)
        ->post(route('tenders.documents.store', $tender), [
            'file' => policyDocxFile(),
            'title' => 'Spec',
            'doc_type' => 'specification',
        ])
        ->assertSessionHasErrors('file');
});

test('Admin tender doc upload rejects oversize PDF', function () {
    $user = policyMpcUser(['tenders.update']);
    $tender = Tender::factory()->create(['status' => 'draft']);

    $this->actingAs($user)
        ->post(route('tenders.documents.store', $tender), [
            'file' => policyOversizePdf(),
            'title' => 'Spec',
            'doc_type' => 'specification',
        ])
        ->assertSessionHasErrors('file');
});

// ── Tender\StoreAddendumRequest (file is nullable) ──────────────────

test('Addendum file rejects non-PDF when provided', function () {
    $user = policyMpcUser(['tenders.issue_addenda']);
    $tender = Tender::factory()->create(['status' => 'published']);

    $this->actingAs($user)
        ->post(route('tenders.addenda.store', $tender), [
            'subject' => 'Schedule update',
            'content_en' => 'New deadline.',
            'extends_deadline' => false,
            'file' => policyDocxFile(),
        ])
        ->assertSessionHasErrors('file');
});

test('Addendum file rejects oversize PDF when provided', function () {
    $user = policyMpcUser(['tenders.issue_addenda']);
    $tender = Tender::factory()->create(['status' => 'published']);

    $this->actingAs($user)
        ->post(route('tenders.addenda.store', $tender), [
            'subject' => 'Schedule update',
            'content_en' => 'New deadline.',
            'extends_deadline' => false,
            'file' => policyOversizePdf(),
        ])
        ->assertSessionHasErrors('file');
});

// ── Tender\StoreTenderRequest (wizard documents.*.file) ─────────────

test('Wizard documents reject non-PDF', function () {
    $user = policyMpcUser(['tenders.create']);

    $this->actingAs($user)
        ->post(route('tenders.store'), tenderPayload([
            'documents' => [
                [
                    'file' => policyDocxFile(),
                    'title' => 'Spec',
                    'doc_type' => 'specification',
                ],
            ],
        ]))
        ->assertSessionHasErrors('documents.0.file');
});

test('Wizard documents reject oversize PDF', function () {
    $user = policyMpcUser(['tenders.create']);

    $this->actingAs($user)
        ->post(route('tenders.store'), tenderPayload([
            'documents' => [
                [
                    'file' => policyOversizePdf(),
                    'title' => 'Spec',
                    'doc_type' => 'specification',
                ],
            ],
        ]))
        ->assertSessionHasErrors('documents.0.file');
});
