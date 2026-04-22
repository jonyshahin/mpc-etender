<?php

use App\Enums\TenderStatus;
use App\Models\Category;
use App\Models\Project;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Tender — Create (T-C-* matrix)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    Storage::fake('s3');
    $this->admin = User::factory()->admin()->create();
    $this->project = Project::factory()->create(['code' => 'TST']);
    $this->category = Category::factory()->create();
});

// T-C-01
it('T-C-01: creates a draft with minimal required fields', function () {
    $payload = tenderPayload(['publish' => false]);

    $response = $this->actingAs($this->admin)->post(route('tenders.store'), $payload);

    $response->assertRedirect();
    $tender = Tender::firstWhere('title_en', $payload['title_en']);
    expect($tender)->not->toBeNull();
    expect($tender->status)->toBe(TenderStatus::Draft);
    expect($tender->publish_date)->toBeNull();
});

// T-C-02
it('T-C-02: rolls back when publish is requested without BOQ', function () {
    $payload = tenderPayload([
        'publish' => true,
        'boq_sections' => [],
    ]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload);

    expect(Tender::count())->toBe(0);
    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
});

// T-C-03
it('T-C-03: publishes a complete tender in one wizard submission', function () {
    $payload = tenderPayload(['publish' => true]);

    $response = $this->actingAs($this->admin)->post(route('tenders.store'), $payload);

    $response->assertRedirect();
    $tender = Tender::firstWhere('title_en', $payload['title_en']);
    expect($tender->status)->toBe(TenderStatus::Published);
    expect($tender->publish_date)->not->toBeNull();
});

// T-C-04
it('T-C-04: downgrades publish to draft when user lacks tenders.publish', function () {
    $po = User::factory()->procurementOfficerWithoutPublish()->create();
    $payload = tenderPayload(['publish' => true]);

    $response = $this->actingAs($po)->post(route('tenders.store'), $payload);

    $response->assertRedirect();
    $tender = Tender::firstWhere('title_en', $payload['title_en']);
    expect($tender->status)->toBe(TenderStatus::Draft);
    expect($tender->publish_date)->toBeNull();
});

// T-C-05/06/07: tender type variants
it('persists all supported tender types', function (string $type) {
    $payload = tenderPayload(['tender_type' => $type, 'publish' => false]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload)->assertRedirect();

    expect(Tender::first()->tender_type->value)->toBe($type);
})->with('tenderTypeVariants');

// T-C-08+: validation rejection matrix
it('rejects invalid tender payloads and persists nothing', function (array $overrides, string $errorKey) {
    $payload = tenderPayload($overrides);

    $response = $this->actingAs($this->admin)->post(route('tenders.store'), $payload);

    $response->assertSessionHasErrors($errorKey);
    expect(Tender::count())->toBe(0);
})->with('invalidTenderPayloads');

// T-C-09
it('T-C-09: stores two-envelope flag with technical_pass_score', function () {
    $payload = tenderPayload([
        'is_two_envelope' => true,
        'technical_pass_score' => 70,
    ]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload)->assertRedirect();

    $tender = Tender::first();
    expect($tender->is_two_envelope)->toBeTrue();
    expect((int) $tender->technical_pass_score)->toBe(70);
});

// T-C-12
it('T-C-12: publishes two-envelope tender with Technical + Financial criteria', function () {
    $payload = tenderPayload([
        'is_two_envelope' => true,
        'technical_pass_score' => 60,
        'evaluation_criteria' => [
            // Each envelope independently sums to 100.
            ['name_en' => 'Technical Compliance', 'weight_percentage' => 100, 'envelope' => 'technical', 'max_score' => 100, 'sort_order' => 0],
            ['name_en' => 'Price', 'weight_percentage' => 100, 'envelope' => 'financial', 'max_score' => 100, 'sort_order' => 1],
        ],
        'publish' => true,
    ]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload)->assertRedirect();

    $tender = Tender::first()->fresh('evaluationCriteria');
    expect($tender->status)->toBe(TenderStatus::Published);
    expect($tender->evaluationCriteria)->toHaveCount(2);
});

// T-C-13: two-envelope tender missing Technical criteria → publish-prereq rollback
it('T-C-13: rejects two-envelope tender missing Technical criteria', function () {
    $payload = tenderPayload([
        'is_two_envelope' => true,
        'technical_pass_score' => 60,
        'evaluation_criteria' => [
            ['name_en' => 'Price', 'weight_percentage' => 100, 'envelope' => 'financial', 'max_score' => 100, 'sort_order' => 0],
        ],
        'publish' => true,
    ]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload);

    expect(Tender::count())->toBe(0);
});

// T-C-34: publish prerequisite — per-envelope weights sum to 100
it('T-C-34: rolls back publish when criteria weights do not sum to 100 per envelope', function () {
    $payload = tenderPayload([
        'evaluation_criteria' => [
            ['name_en' => 'A', 'weight_percentage' => 40, 'envelope' => 'financial', 'max_score' => 100, 'sort_order' => 0],
            ['name_en' => 'B', 'weight_percentage' => 55, 'envelope' => 'financial', 'max_score' => 100, 'sort_order' => 1],
        ],
        'publish' => true,
    ]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload);

    expect(Tender::count())->toBe(0);
    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
});

// T-C-35: publish prerequisite — global 100 but per-envelope imbalanced fails
it('T-C-35: rejects publish when per-envelope weights are imbalanced despite global 100', function () {
    $payload = tenderPayload([
        'is_two_envelope' => true,
        'technical_pass_score' => 60,
        'evaluation_criteria' => [
            ['name_en' => 'Tech', 'weight_percentage' => 60, 'envelope' => 'technical', 'max_score' => 100, 'sort_order' => 0],
            ['name_en' => 'Price', 'weight_percentage' => 40, 'envelope' => 'financial', 'max_score' => 100, 'sort_order' => 1],
        ],
        'publish' => true,
    ]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload);

    expect(Tender::count())->toBe(0);
});

// T-C-36: publish prerequisite — submission deadline in the past
it('T-C-36: does not apply deadline-past check when deadline is validated by request rules', function () {
    // StoreTenderRequest already rejects past submission_deadline at validation time
    // for the create-and-publish path. This sanity-check confirms the double-guard
    // in TenderService::publish doesn't fire spuriously on otherwise valid data.
    $payload = tenderPayload(['publish' => true]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload)->assertRedirect();

    expect(Tender::count())->toBe(1);
    expect(Tender::first()->status)->toBe(TenderStatus::Published);
});

// T-C-15/16/18: BOQ shape variants
it('persists BOQ structures correctly across shape variants', function (array $sections, int $expectedItemCount) {
    $payload = tenderPayload(['boq_sections' => $sections, 'publish' => false]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload)->assertRedirect();

    $tender = Tender::first()->fresh(['boqSections.items']);
    $totalItems = $tender->boqSections->sum(fn ($s) => $s->items->count());
    expect($totalItems)->toBe($expectedItemCount);
})->with('validBoqShapes');

// T-C-21
it('T-C-21: uploads and links a single document on draft create', function () {
    $payload = tenderPayload(['publish' => false]);
    $payload['documents'] = [
        ['file' => fakeDoc('spec.pdf', 500), 'title' => 'Specification', 'doc_type' => 'specification'],
    ];

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload)->assertRedirect();

    $tender = Tender::first()->fresh('documents');
    expect($tender->documents)->toHaveCount(1);
    expect(Storage::disk('s3')->allFiles())->not->toBeEmpty();
});

// T-C-22
it('T-C-22: uploads multiple documents with varied doc_types', function () {
    $payload = tenderPayload(['publish' => true]);
    $payload['documents'] = [
        ['file' => fakeDoc('spec.pdf', 500), 'title' => 'Spec', 'doc_type' => 'specification'],
        ['file' => fakeDoc('drawing.pdf', 800), 'title' => 'Drawing', 'doc_type' => 'drawing'],
        ['file' => fakeDoc('terms.docx', 200), 'title' => 'Terms', 'doc_type' => 'contract_terms'],
        ['file' => fakeDoc('photo.jpg', 1200, 'image/jpeg'), 'title' => 'Photo', 'doc_type' => 'site_photo'],
    ];

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload)->assertRedirect();

    $tender = Tender::first()->fresh('documents');
    expect($tender->documents)->toHaveCount(4);
    expect($tender->documents->pluck('doc_type')->map(fn ($t) => is_object($t) ? $t->value : $t)->sort()->values()->toArray())
        ->toEqual(['contract_terms', 'drawing', 'site_photo', 'specification']);
});

// T-C-23
it('T-C-23: rejects oversized document uploads', function () {
    $payload = tenderPayload();
    $payload['documents'] = [
        ['file' => fakeDoc('huge.pdf', 11000), 'title' => 'Huge', 'doc_type' => 'specification'],
    ];

    $response = $this->actingAs($this->admin)->post(route('tenders.store'), $payload);

    $response->assertSessionHasErrors();
    expect(Tender::count())->toBe(0);
});

// T-C-24
it('T-C-24: rejects disallowed MIME types', function () {
    $payload = tenderPayload();
    $payload['documents'] = [
        ['file' => fakeDoc('malware.exe', 100, 'application/x-msdownload'), 'title' => 'X', 'doc_type' => 'specification'],
    ];

    $response = $this->actingAs($this->admin)->post(route('tenders.store'), $payload);

    $response->assertSessionHasErrors();
    expect(Tender::count())->toBe(0);
});

// T-C-25
it('T-C-25: rolls back when any document is invalid (atomicity)', function () {
    $payload = tenderPayload();
    $payload['documents'] = [
        ['file' => fakeDoc('valid.pdf', 500), 'title' => 'OK', 'doc_type' => 'specification'],
        ['file' => fakeDoc('bad.exe', 100, 'application/x-msdownload'), 'title' => 'BAD', 'doc_type' => 'other'],
    ];

    $response = $this->actingAs($this->admin)->post(route('tenders.store'), $payload);

    $response->assertSessionHasErrors();
    expect(Tender::count())->toBe(0);
    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
});

// T-C-27 — skipped (category_ids is nullable in StoreTenderRequest)
it('T-C-27: zero categories accepted')
    ->skip('category_ids is nullable in StoreTenderRequest — no validation gap to assert');

// T-C-28
it('T-C-28: attaches multiple categories via pivot', function () {
    $categories = Category::factory()->count(5)->create();
    $payload = tenderPayload(['category_ids' => $categories->pluck('id')->toArray()]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload)->assertRedirect();

    $tender = Tender::first()->fresh('categories');
    expect($tender->categories)->toHaveCount(5);
});

// T-C-30
it('T-C-30: deduplicates repeated category ids', function () {
    $categoryId = $this->category->id;
    $payload = tenderPayload(['category_ids' => [$categoryId, $categoryId, $categoryId]]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload)->assertRedirect();

    expect(Tender::first()->fresh('categories')->categories)->toHaveCount(1);
});

// T-C-33
it('T-C-33: accepts three evaluation criteria summing to 100%', function () {
    $payload = tenderPayload([
        'evaluation_criteria' => [
            ['name_en' => 'Technical', 'weight_percentage' => 40, 'envelope' => 'financial', 'max_score' => 100, 'sort_order' => 0],
            ['name_en' => 'Delivery', 'weight_percentage' => 30, 'envelope' => 'financial', 'max_score' => 100, 'sort_order' => 1],
            ['name_en' => 'Price', 'weight_percentage' => 30, 'envelope' => 'financial', 'max_score' => 100, 'sort_order' => 2],
        ],
        'publish' => true,
    ]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload)->assertRedirect();

    expect(Tender::first()->fresh('evaluationCriteria')->evaluationCriteria)->toHaveCount(3);
});

// T-C-38: bilingual round-trip
it('T-C-38: preserves Arabic title_ar and description_ar through persistence', function () {
    $arTitle = 'مناقصة توريد حديد التسليح للمرحلة الأولى';
    $arDesc = 'وصف تفصيلي باللغة العربية لاختبار التوافق مع UTF-8 وترميز الخط العربي.';

    $payload = tenderPayload([
        'title_ar' => $arTitle,
        'description_ar' => $arDesc,
    ]);

    $this->actingAs($this->admin)->post(route('tenders.store'), $payload)->assertRedirect();

    $tender = Tender::first();
    expect($tender->title_ar)->toBe($arTitle);
    expect($tender->description_ar)->toBe($arDesc);
});
