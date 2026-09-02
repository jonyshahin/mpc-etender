<?php

use App\Enums\BidDocType;
use App\Enums\BidStatus;
use App\Enums\TenderDocType;
use App\Enums\TenderType;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Clarification;
use App\Models\Tender;
use App\Models\TenderDocument;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * A qualified vendor with one category, and a published tender in that same
 * category — the baseline "this vendor may look at this tender" fixture.
 *
 * @return array{0: Vendor, 1: Tender}
 */
function browseFixture(array $tenderAttributes = []): array
{
    $tender = Tender::factory()
        ->published()
        ->withBoq(1, 2)
        ->withCategories(1)
        ->create($tenderAttributes);

    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $vendor->categories()->attach($tender->categories()->first()->id);

    return [$vendor, $tender];
}

// ── The employer's own numbers ──

test('browse list does not ship the employer estimate or internal notes', function () {
    [$vendor] = browseFixture([
        'estimated_value' => 4_250_000,
        'notes_internal' => 'Board approved a ceiling of 4.4M. Do not disclose.',
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.tenders.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('tenders.data', 1)
        ->missing('tenders.data.0.estimated_value')
        ->missing('tenders.data.0.notes_internal')
        ->missing('tenders.data.0.technical_pass_score')
        ->missing('tenders.data.0.created_by')
    );
});

test('tender detail does not ship the employer estimate or internal notes', function () {
    [$vendor, $tender] = browseFixture([
        'estimated_value' => 4_250_000,
        'notes_internal' => 'Board approved a ceiling of 4.4M. Do not disclose.',
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.tenders.show', $tender));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->missing('tender.estimated_value')
        ->missing('tender.notes_internal')
        ->missing('tender.technical_pass_score')
        ->missing('tender.created_by')
        ->missing('tender.cancelled_reason')
    );
});

test('tender detail does not ship S3 paths for documents', function () {
    [$vendor, $tender] = browseFixture();
    TenderDocument::factory()->create(['tender_id' => $tender->id]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.tenders.show', $tender));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('tender.documents', 1)
        ->missing('tender.documents.0.file_path')
        ->missing('tender.documents.0.uploaded_by')
    );
});

test('a published clarification does not carry the asking vendor identity', function () {
    [$vendor, $tender] = browseFixture();
    $rival = Vendor::factory()->qualified()->create();

    Clarification::factory()->create([
        'tender_id' => $tender->id,
        'asked_by' => $rival->id,
        'question' => 'Is scaffolding included in item 2.1?',
        'answer' => 'Yes.',
        'is_published' => true,
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.tenders.show', $tender));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('tender.clarifications', 1)
        ->missing('tender.clarifications.0.asked_by')
    );
});

// ── Authorization ──

test('a vendor cannot open a draft tender by guessing its id', function () {
    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $draft = Tender::factory()->draft()->withCategories(1)->create([
        'notes_internal' => 'Not published yet.',
    ]);
    $vendor->categories()->attach($draft->categories()->first()->id);

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.tenders.show', $draft))
        ->assertNotFound();
});

test('a vendor cannot open a published tender outside their categories', function () {
    $tender = Tender::factory()->published()->withCategories(1)->create();

    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $vendor->categories()->attach(Category::factory()->create()->id);

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.tenders.show', $tender))
        ->assertNotFound();
});

test('a vendor who already bid keeps access after the tender closes', function () {
    // Access must not hinge on the live "can you still bid" test: a vendor
    // needs to revisit the tender they bid on after the deadline.
    [$vendor, $tender] = browseFixture();
    $tender->update(['submission_deadline' => now()->subDay()]);
    Bid::factory()->create(['tender_id' => $tender->id, 'vendor_id' => $vendor->id]);

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.tenders.show', $tender))
        ->assertOk();
});

// ── Superseded documents ──

test('a superseded document version is not offered to bid against', function () {
    [$vendor, $tender] = browseFixture();
    TenderDocument::factory()->create([
        'tender_id' => $tender->id,
        'title' => 'Structural Spec',
        'version' => 1,
        'is_current' => false,
    ]);
    TenderDocument::factory()->create([
        'tender_id' => $tender->id,
        'title' => 'Structural Spec',
        'version' => 2,
        'is_current' => true,
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.tenders.show', $tender));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('tender.documents', 1)
        ->where('tender.documents.0.version', 2)
    );
});

// ── The download the page already links to ──

test('a vendor can download a document on a tender they may see', function () {
    Storage::fake('s3');

    [$vendor, $tender] = browseFixture();
    $document = TenderDocument::factory()->create([
        'tender_id' => $tender->id,
        'file_path' => 'tender-docs/spec.pdf',
        'title' => 'Structural Spec',
    ]);
    Storage::disk('s3')->put('tender-docs/spec.pdf', '%PDF-1.4 fake');

    // The Show page has always rendered this link; the route never existed,
    // so every "Download" button on a vendor's tender page was a 404.
    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.tenders.documents.download', [$tender, $document]))
        ->assertOk()
        ->assertDownload('Structural Spec.pdf');
});

test('downloading a tender document is written to the access log', function () {
    Storage::fake('s3');

    [$vendor, $tender] = browseFixture();
    $document = TenderDocument::factory()->create([
        'tender_id' => $tender->id,
        'file_path' => 'tender-docs/spec.pdf',
    ]);
    Storage::disk('s3')->put('tender-docs/spec.pdf', '%PDF-1.4 fake');

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.tenders.documents.download', [$tender, $document]))
        ->assertOk();

    $this->assertDatabaseHas('document_access_logs', [
        'vendor_id' => $vendor->id,
        'document_type' => TenderDocument::class,
        'document_id' => $document->id,
        'action' => 'downloaded',
    ]);
});

test('a document belonging to another tender is not reachable through this one', function () {
    [$vendor, $tender] = browseFixture();
    $otherDocument = TenderDocument::factory()->create();

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.tenders.documents.download', [$tender, $otherDocument]))
        ->assertNotFound();
});

test('a vendor cannot download a document from a tender they may not see', function () {
    $tender = Tender::factory()->published()->withCategories(1)->create();
    $document = TenderDocument::factory()->create(['tender_id' => $tender->id]);

    $vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
    $vendor->categories()->attach(Category::factory()->create()->id);

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.tenders.documents.download', [$tender, $document]))
        ->assertNotFound();
});

// ── Filters, counts and sorting ──

test('browse exposes status counts and a summary alongside the rows', function () {
    [$vendor, $tender] = browseFixture();
    $category = $tender->categories()->first();

    Tender::factory()->published()->create(['submission_deadline' => now()->addDays(2)])
        ->categories()->attach($category->id);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.tenders.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('summary.open')
        ->has('summary.closing_soon')
        ->has('summary.bid_started')
        ->has('filters.sort')
        ->has('filters.direction')
    );
});

test('an unknown sort column falls back instead of erroring', function () {
    [$vendor] = browseFixture();

    $this->actingAs($vendor, 'vendor')
        ->get(route('vendor.tenders.index', ['sort' => 'notes_internal', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.sort', 'submission_deadline'));
});

test('rival bid counts are not shipped to the browse list', function () {
    [$vendor, $tender] = browseFixture();
    Bid::factory()->count(3)->create(['tender_id' => $tender->id]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.tenders.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->missing('tenders.data.0.bids_count'));
});

// ── Label keys, which is where the enum literals used to live ──

test('the detail page ships label keys the catalogues actually carry', function () {
    [$vendor, $tender] = browseFixture(['tender_type' => TenderType::DirectInvitation]);
    TenderDocument::factory()->create([
        'tender_id' => $tender->id,
        'doc_type' => TenderDocType::Specification,
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.tenders.show', $tender));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('tender.tender_type_label_key', 'tender.type_direct_invitation')
        ->where('tender.documents.0.doc_type_label_key', 'tender.doc_specification')
    );
});

test('every enum label key resolves in all three catalogues', function () {
    // The pages used to build these keys by concatenation, or list the labels
    // as literals — either way a renamed case rendered the raw key to a vendor.
    $keys = [
        ...array_map(fn (TenderType $c) => $c->labelKey(), TenderType::cases()),
        ...array_map(fn (TenderDocType $c) => $c->labelKey(), TenderDocType::cases()),
        ...array_map(fn (BidDocType $c) => $c->labelKey(), BidDocType::cases()),
        ...array_map(fn (BidStatus $c) => $c->labelKey(), BidStatus::cases()),
    ];

    foreach (['en', 'ar', 'ku'] as $locale) {
        $catalogue = json_decode(file_get_contents(lang_path("{$locale}.json")), true);

        foreach ($keys as $key) {
            expect(array_key_exists($key, $catalogue))
                ->toBeTrue("{$key} missing from {$locale}.json");
        }
    }
});

test('the bid page offers each envelope only the doc types that belong to it', function () {
    [$vendor, $tender] = browseFixture(['is_two_envelope' => true]);
    $bid = Bid::factory()->create([
        'tender_id' => $tender->id,
        'vendor_id' => $vendor->id,
        'status' => BidStatus::Draft,
        'is_sealed' => false,
        'total_amount' => null,
        'encrypted_pricing_data' => null,
        'submitted_at' => null,
    ]);

    $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.bids.show', $bid));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        // Financial takes the schedule and Other; a method statement does not
        // belong in the envelope that carries the prices.
        ->has('docTypes.financial', 2)
        ->where('docTypes.financial.0.value', BidDocType::FinancialSchedule->value)
        ->has('docTypes.technical', 4)
        ->has('docTypes.single', count(BidDocType::cases()))
    );
});
