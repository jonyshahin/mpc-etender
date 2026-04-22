<?php

use App\Enums\TenderStatus;
use App\Models\Tender;
use App\Models\TenderDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Tender — Update (T-U-*)
|--------------------------------------------------------------------------
| Route: PUT /tenders/{tender} — requires FULL payload.
| Policy::update requires: status === Draft AND user is on the project.
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    Storage::fake('s3');
    $this->admin = User::factory()->admin()->create();
});

function validUpdatePayload(Tender $tender, array $overrides = []): array
{
    return array_merge([
        'title_en' => $tender->title_en,
        'title_ar' => $tender->title_ar,
        'description_en' => $tender->description_en,
        'description_ar' => $tender->description_ar,
        'tender_type' => is_object($tender->tender_type) ? $tender->tender_type->value : $tender->tender_type,
        'currency' => 'USD',
        'estimated_value' => $tender->estimated_value,
        'is_two_envelope' => (bool) $tender->is_two_envelope,
        'technical_pass_score' => $tender->technical_pass_score,
        'submission_deadline' => now()->addDays(30)->format('Y-m-d H:i:s'),
        'opening_date' => now()->addDays(31)->format('Y-m-d H:i:s'),
    ], $overrides);
}

// T-U-01
it('T-U-01: updates title_en on a Draft tender', function () {
    $tender = Tender::factory()->draft()->create(['title_en' => 'Original Title']);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $response = $this->actingAs($this->admin)
        ->put(route('tenders.update', $tender), validUpdatePayload($tender, ['title_en' => 'Updated Title']));

    $response->assertRedirect();
    expect($tender->fresh()->title_en)->toBe('Updated Title');
});

// T-U-02
it('T-U-02: updates estimated_value without touching BOQ or criteria', function () {
    $tender = Tender::factory()->draft()->withBoqAndCriteria()->create(['estimated_value' => 100000]);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);
    $originalBoqCount = $tender->boqSections()->count();
    $originalCriteriaCount = $tender->evaluationCriteria()->count();

    $this->actingAs($this->admin)
        ->put(route('tenders.update', $tender), validUpdatePayload($tender, ['estimated_value' => 500000]))
        ->assertRedirect();

    $tender->refresh();
    expect((float) $tender->estimated_value)->toBe(500000.0);
    expect($tender->boqSections()->count())->toBe($originalBoqCount);
    expect($tender->evaluationCriteria()->count())->toBe($originalCriteriaCount);
});

// T-U-03
it('T-U-03: ignores direct status mutation via update payload', function () {
    $tender = Tender::factory()->draft()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $this->actingAs($this->admin)
        ->put(route('tenders.update', $tender), validUpdatePayload($tender, ['status' => 'published']));

    // status is not in UpdateTenderRequest rules, so validated() strips it.
    expect($tender->fresh()->status)->toBe(TenderStatus::Draft);
});

// T-U-04
it('T-U-04: locks Published tenders against direct field updates', function () {
    $tender = Tender::factory()->published()->create(['title_en' => 'Locked Title']);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $this->actingAs($this->admin)
        ->put(route('tenders.update', $tender), validUpdatePayload($tender, ['title_en' => 'Attempted Change']));

    expect($tender->fresh()->title_en)->toBe('Locked Title');
});

// T-U-06 — skipped (BidLineItem not in this app; bid-BOQ link lives on BidBoqPrice)
it('T-U-06: prevents removing a BOQ item that has bid price entries')
    ->skip('Uses BidLineItem; this app stores bid-BOQ prices via BidBoqPrice — file ETENDER-TEST-BOQ-GUARD to port');

// T-U-08
it('T-U-08: uploads an additional document onto a Draft tender', function () {
    $tender = Tender::factory()->draft()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);
    $originalCount = $tender->documents()->count();

    $response = $this->actingAs($this->admin)->post(
        route('tenders.documents.store', $tender),
        [
            'file' => fakeDoc('addendum.pdf', 300),
            'title' => 'Late Addition',
            'doc_type' => 'specification',
        ]
    );

    $response->assertRedirect();
    expect($tender->documents()->count())->toBe($originalCount + 1);
});

// T-U-09
it('T-U-09: deletes a document from a Draft tender and removes the file', function () {
    $tender = Tender::factory()->draft()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);
    $document = TenderDocument::factory()->create(['tender_id' => $tender->id]);
    Storage::disk('s3')->put($document->file_path, 'test-content');

    $response = $this->actingAs($this->admin)
        ->delete(route('tenders.documents.destroy', [$tender, $document]));

    $response->assertRedirect();
    expect($tender->documents()->count())->toBe(0);
    expect(Storage::disk('s3')->exists($document->file_path))->toBeFalse();
});
