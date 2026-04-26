<?php

use App\Enums\TenderStatus;
use App\Exceptions\TenderPublishException;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Services\TenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // BUG-22 fixture changes route document files through FileUploadService,
    // which writes to the s3 disk — fake it so tests don't hit a live bucket.
    Storage::fake('s3');
});

function createTenderCreator(array $permissionSlugs = ['tenders.create', 'tenders.publish']): array
{
    $role = Role::factory()->create();

    foreach ($permissionSlugs as $slug) {
        $permission = Permission::create([
            'name' => ucwords(str_replace('.', ' ', $slug)),
            'slug' => $slug,
            'module' => explode('.', $slug)[0],
        ]);
        $role->permissions()->attach($permission->id);
    }

    $user = User::factory()->create(['role_id' => $role->id]);
    $project = Project::factory()->create(['created_by' => $user->id]);

    $user->projects()->attach($project->id, ['project_role' => 'project_manager']);

    return [$user, $project];
}

function validTenderPayload(string $projectId, array $overrides = []): array
{
    return array_merge([
        'project_id' => $projectId,
        'title_en' => 'Post-Fix Publish Test',
        'title_ar' => 'اختبار النشر بعد الإصلاح',
        'description_en' => 'Diagnostic.',
        'tender_type' => 'open',
        'estimated_value' => 100000,
        'currency' => 'USD',
        'is_two_envelope' => false,
        'submission_deadline' => now()->addDays(30)->format('Y-m-d H:i:s'),
        'opening_date' => now()->addDays(31)->format('Y-m-d H:i:s'),
        // BOQ + criteria are required for publish to succeed (prerequisites in TenderService::publish).
        'boq_sections' => [[
            'title_en' => 'Main',
            'sort_order' => 0,
            'items' => [['item_code' => 'A.1', 'description_en' => 'Item', 'unit' => 'Ton', 'quantity' => 10, 'sort_order' => 0]],
        ]],
        'evaluation_criteria' => [
            ['name_en' => 'Price', 'weight_percentage' => 100, 'envelope' => 'financial', 'max_score' => 100, 'sort_order' => 0],
        ],
        // BUG-22: at least one document is now a publish prereq. Tests
        // that need to assert "no document" behavior override this to [].
        'documents' => [
            [
                'file' => UploadedFile::fake()->create('spec.pdf', 200, 'application/pdf'),
                'title' => 'Default Specification',
                'doc_type' => 'specification',
            ],
        ],
    ], $overrides);
}

test('tender saves as draft when publish flag is false', function () {
    [$user, $project] = createTenderCreator();

    $response = $this->actingAs($user)->post(
        route('tenders.store'),
        validTenderPayload($project->id, ['publish' => false]),
    );

    $response->assertRedirect();
    $tender = Tender::where('title_en', 'Post-Fix Publish Test')->first();
    expect($tender)->not->toBeNull();
    expect($tender->status)->toBe(TenderStatus::Draft);
    expect($tender->publish_date)->toBeNull();
});

test('tender saves as draft when publish flag is omitted', function () {
    [$user, $project] = createTenderCreator();

    $response = $this->actingAs($user)->post(
        route('tenders.store'),
        validTenderPayload($project->id),
    );

    $response->assertRedirect();
    $tender = Tender::where('title_en', 'Post-Fix Publish Test')->first();
    expect($tender->status)->toBe(TenderStatus::Draft);
    expect($tender->publish_date)->toBeNull();
});

test('tender is published when publish flag is true', function () {
    [$user, $project] = createTenderCreator();

    $response = $this->actingAs($user)->post(
        route('tenders.store'),
        validTenderPayload($project->id, ['publish' => true]),
    );

    $response->assertRedirect();
    $tender = Tender::where('title_en', 'Post-Fix Publish Test')->first();
    expect($tender)->not->toBeNull();
    expect($tender->status)->toBe(TenderStatus::Published);
    expect($tender->publish_date)->not->toBeNull();
});

test('publish flag accepts FormData-style truthy values', function (mixed $truthyValue) {
    [$user, $project] = createTenderCreator();

    $response = $this->actingAs($user)->post(
        route('tenders.store'),
        validTenderPayload($project->id, ['publish' => $truthyValue]),
    );

    $response->assertRedirect();
    $tender = Tender::where('title_en', 'Post-Fix Publish Test')->first();
    expect($tender->status)->toBe(TenderStatus::Published);
})->with([
    '"1" string' => '1',
    '"true" string' => 'true',
    '1 int' => 1,
    'true bool' => true,
]);

test('validation error message uses user-readable label, not the raw dotted path (BUG-14)', function () {
    [$user, $project] = createTenderCreator();

    $payload = validTenderPayload($project->id);
    // Force a required_with violation on a deeply-nested array field whose
    // raw error key would otherwise leak as "boq_sections.0.items.0.description_en".
    unset($payload['boq_sections'][0]['items'][0]['description_en']);

    $response = $this->actingAs($user)->post(
        route('tenders.store'),
        $payload,
    );

    $response->assertSessionHasErrors('boq_sections.0.items.0.description_en');

    $message = session('errors')->first('boq_sections.0.items.0.description_en');

    // Raw structural path must NOT appear in user-facing copy.
    expect($message)->not->toContain('boq_sections');
    expect($message)->not->toContain('items');
    expect($message)->not->toContain('description_en');
    // Translatable label IS substituted.
    expect($message)->toContain('Description');
    // The noisy "when X is present" suffix is dropped — see messages() override.
    expect($message)->not->toContain('when');
});

test('missing evaluation_criteria.max_score returns the validation key in session errors (BUG-11)', function () {
    [$user, $project] = createTenderCreator();

    $payload = validTenderPayload($project->id, ['publish' => true]);
    unset($payload['evaluation_criteria'][0]['max_score']);

    $response = $this->actingAs($user)->post(
        route('tenders.store'),
        $payload,
    );

    // Validation failure should surface the dotted error key the wizard now
    // renders inline. If this assertion ever stops matching the React side's
    // FieldError path string, BUG-11 is regressing.
    $response->assertSessionHasErrors('evaluation_criteria.0.max_score');
    expect(Tender::where('title_en', 'Post-Fix Publish Test')->exists())->toBeFalse();
});

test('rejects publish when tender has no documents (BUG-22)', function () {
    // Build a draft tender that meets every OTHER publish prereq:
    // BOQ + criteria + weights summing to 100 + future deadline.
    // Then verify the service-level publish gate refuses to flip the
    // status because zero documents are attached.
    $tender = Tender::factory()
        ->draft()
        ->withBoq()
        ->withCriteria()
        // NOTE: deliberately no ->withDocument() — this is the gap.
        ->create([
            'submission_deadline' => now()->addDays(14),
            'opening_date' => now()->addDays(15),
        ]);

    $service = app(TenderService::class);

    expect(fn () => $service->publish($tender))
        ->toThrow(TenderPublishException::class, __('messages.publish_reason_no_documents'));

    // Status should remain Draft — the throw must happen before any update.
    expect($tender->fresh()->status)->toBe(TenderStatus::Draft);
});

test('publish is downgraded to draft when user lacks tenders.publish permission', function () {
    [$user, $project] = createTenderCreator(['tenders.create']);

    $response = $this->actingAs($user)->post(
        route('tenders.store'),
        validTenderPayload($project->id, ['publish' => true]),
    );

    $response->assertRedirect();
    $tender = Tender::where('title_en', 'Post-Fix Publish Test')->first();
    expect($tender)->not->toBeNull();
    expect($tender->status)->toBe(TenderStatus::Draft);
    expect($tender->publish_date)->toBeNull();
});
