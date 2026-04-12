<?php

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createAuthorizedUser(array $permissionSlugs = []): array
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

    $user->projects()->attach($project->id, [
        'project_role' => 'project_manager',
    ]);

    return [$user, $project];
}

test('authenticated user can create a tender as draft', function () {
    [$user, $project] = createAuthorizedUser(['tenders.create']);

    $tenderData = [
        'project_id' => $project->id,
        'title_en' => 'Supply and Installation of Fire Safety Systems',
        'title_ar' => 'توريد وتركيب أنظمة السلامة من الحريق',
        'description_en' => 'Full fire safety system including alarms, sprinklers, and emergency exits.',
        'tender_type' => 'open',
        'estimated_value' => 250000.00,
        'currency' => 'USD',
        'submission_deadline' => now()->addMonths(2)->format('Y-m-d H:i:s'),
        'opening_date' => now()->addMonths(2)->addDay()->format('Y-m-d H:i:s'),
        'is_two_envelope' => false,
    ];

    $response = $this->actingAs($user)
        ->post(route('tenders.store'), $tenderData);

    $response->assertRedirect();

    $this->assertDatabaseHas('tenders', [
        'project_id' => $project->id,
        'title_en' => 'Supply and Installation of Fire Safety Systems',
        'status' => 'draft',
        'created_by' => $user->id,
    ]);
});

test('user can upload a document to a draft tender', function () {
    Storage::fake('s3');

    [$user, $project] = createAuthorizedUser(['tenders.create', 'tenders.update']);

    $tender = Tender::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'status' => 'draft',
    ]);

    $file = new UploadedFile(
        path: base_path('tests/fixtures/test-document.pdf'),
        originalName: 'site-plan.pdf',
        mimeType: 'application/pdf',
        test: true,
    );

    $response = $this->actingAs($user)
        ->post(route('tenders.documents.store', $tender), [
            'file' => $file,
            'title' => 'Site Plan Document',
            'doc_type' => 'specification',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('tender_documents', [
        'tender_id' => $tender->id,
        'title' => 'Site Plan Document',
        'doc_type' => 'specification',
        'mime_type' => 'application/pdf',
        'version' => 1,
        'is_current' => true,
        'uploaded_by' => $user->id,
    ]);
});

test('full flow: create tender then upload document', function () {
    Storage::fake('s3');

    [$user, $project] = createAuthorizedUser(['tenders.create', 'tenders.update']);

    $tenderData = [
        'project_id' => $project->id,
        'title_en' => 'MPC Village Site Development',
        'description_en' => 'Complete site development including grading, utilities, and landscaping.',
        'tender_type' => 'open',
        'estimated_value' => 500000.00,
        'currency' => 'USD',
        'submission_deadline' => now()->addMonths(2)->format('Y-m-d H:i:s'),
        'opening_date' => now()->addMonths(2)->addDay()->format('Y-m-d H:i:s'),
        'is_two_envelope' => true,
        'technical_pass_score' => 70,
    ];

    $response = $this->actingAs($user)
        ->post(route('tenders.store'), $tenderData);

    $response->assertRedirect();

    $tender = Tender::where('title_en', 'MPC Village Site Development')->first();
    expect($tender)->not->toBeNull();
    expect($tender->status->value)->toBe('draft');
    expect($tender->is_two_envelope)->toBeTrue();

    $file = new UploadedFile(
        path: base_path('tests/fixtures/test-document.pdf'),
        originalName: 'سايت بلان mpc village.pdf',
        mimeType: 'application/pdf',
        test: true,
    );

    $docResponse = $this->actingAs($user)
        ->post(route('tenders.documents.store', $tender), [
            'file' => $file,
            'title' => 'Site Plan - MPC Village',
            'doc_type' => 'drawing',
        ]);

    $docResponse->assertRedirect();

    $this->assertDatabaseHas('tender_documents', [
        'tender_id' => $tender->id,
        'title' => 'Site Plan - MPC Village',
        'doc_type' => 'drawing',
        'version' => 1,
        'is_current' => true,
    ]);

    expect($tender->documents()->count())->toBe(1);
});

test('uploading a second document with same title increments version', function () {
    Storage::fake('s3');

    [$user, $project] = createAuthorizedUser(['tenders.create', 'tenders.update']);

    $tender = Tender::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'status' => 'draft',
    ]);

    $file = new UploadedFile(
        path: base_path('tests/fixtures/test-document.pdf'),
        originalName: 'specs-v1.pdf',
        mimeType: 'application/pdf',
        test: true,
    );

    $this->actingAs($user)
        ->post(route('tenders.documents.store', $tender), [
            'file' => $file,
            'title' => 'Technical Specifications',
            'doc_type' => 'specification',
        ]);

    $file2 = new UploadedFile(
        path: base_path('tests/fixtures/test-document.pdf'),
        originalName: 'specs-v2.pdf',
        mimeType: 'application/pdf',
        test: true,
    );

    $this->actingAs($user)
        ->post(route('tenders.documents.store', $tender), [
            'file' => $file2,
            'title' => 'Technical Specifications',
            'doc_type' => 'specification',
        ]);

    $documents = $tender->documents()->orderBy('version')->get();
    expect($documents)->toHaveCount(2);
    expect($documents[0]->version)->toBe(1);
    expect($documents[0]->is_current)->toBeFalse();
    expect($documents[1]->version)->toBe(2);
    expect($documents[1]->is_current)->toBeTrue();
});

test('wizard persists documents during tender creation', function () {
    Storage::fake('s3');

    [$user, $project] = createAuthorizedUser(['tenders.create']);

    $response = $this->actingAs($user)
        ->post(route('tenders.store'), [
            'project_id' => $project->id,
            'title_en' => 'Tender With Wizard Documents',
            'tender_type' => 'open',
            'estimated_value' => 100000,
            'currency' => 'USD',
            'submission_deadline' => now()->addMonths(2)->format('Y-m-d H:i:s'),
            'opening_date' => now()->addMonths(2)->addDay()->format('Y-m-d H:i:s'),
            'is_two_envelope' => false,
            'documents' => [
                [
                    'file' => UploadedFile::fake()->create('specs.pdf', 1024, 'application/pdf'),
                    'title' => 'Project Specifications',
                    'doc_type' => 'specification',
                ],
                [
                    'file' => UploadedFile::fake()->create('drawings.pdf', 2048, 'application/pdf'),
                    'title' => 'Site Drawings',
                    'doc_type' => 'drawing',
                ],
            ],
        ]);

    $response->assertRedirect();

    $tender = Tender::where('title_en', 'Tender With Wizard Documents')->first();
    expect($tender)->not->toBeNull();
    expect($tender->documents()->count())->toBe(2);
    expect($tender->documents()->where('title', 'Project Specifications')->exists())->toBeTrue();
    expect($tender->documents()->where('title', 'Site Drawings')->exists())->toBeTrue();

    $doc = $tender->documents()->where('title', 'Project Specifications')->first();
    expect($doc->doc_type->value)->toBe('specification');
    expect($doc->version)->toBe(1);
    expect($doc->is_current)->toBeTrue();
    expect($doc->uploaded_by)->toBe($user->id);

    Storage::disk('s3')->assertExists($doc->file_path);
});

test('wizard works without documents', function () {
    [$user, $project] = createAuthorizedUser(['tenders.create']);

    $response = $this->actingAs($user)
        ->post(route('tenders.store'), [
            'project_id' => $project->id,
            'title_en' => 'Tender Without Documents',
            'tender_type' => 'open',
            'currency' => 'USD',
            'submission_deadline' => now()->addMonths(2)->format('Y-m-d H:i:s'),
            'opening_date' => now()->addMonths(2)->addDay()->format('Y-m-d H:i:s'),
            'is_two_envelope' => false,
        ]);

    $response->assertRedirect();

    $tender = Tender::where('title_en', 'Tender Without Documents')->first();
    expect($tender)->not->toBeNull();
    expect($tender->documents()->count())->toBe(0);
});

test('user without permission cannot create a tender', function () {
    [$user, $project] = createAuthorizedUser([]);

    $response = $this->actingAs($user)
        ->post(route('tenders.store'), [
            'project_id' => $project->id,
            'title_en' => 'Unauthorized Tender',
            'tender_type' => 'open',
            'currency' => 'USD',
            'submission_deadline' => now()->addMonths(2)->format('Y-m-d H:i:s'),
            'opening_date' => now()->addMonths(2)->addDay()->format('Y-m-d H:i:s'),
            'is_two_envelope' => false,
        ]);

    $response->assertForbidden();
});

test('user without permission cannot upload document to tender', function () {
    Storage::fake('s3');

    $role = Role::factory()->create();
    $user = User::factory()->create(['role_id' => $role->id]);
    $tender = Tender::factory()->create(['status' => 'draft']);

    $file = new UploadedFile(
        path: base_path('tests/fixtures/test-document.pdf'),
        originalName: 'unauthorized.pdf',
        mimeType: 'application/pdf',
        test: true,
    );

    $response = $this->actingAs($user)
        ->post(route('tenders.documents.store', $tender), [
            'file' => $file,
            'title' => 'Unauthorized Upload',
            'doc_type' => 'other',
        ]);

    $response->assertForbidden();
});

test('document upload validates required fields', function () {
    Storage::fake('s3');

    [$user, $project] = createAuthorizedUser(['tenders.create', 'tenders.update']);

    $tender = Tender::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($user)
        ->post(route('tenders.documents.store', $tender), []);

    $response->assertSessionHasErrors(['file', 'title', 'doc_type']);
});
