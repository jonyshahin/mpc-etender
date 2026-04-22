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
