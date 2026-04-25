<?php

use App\Enums\TenderStatus;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
