<?php

use App\Enums\TenderStatus;
use App\Models\Tender;
use App\Models\User;
use App\Services\TenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Tender — Lifecycle transitions (T-P-*, T-A-*, T-Q-*, T-X-*, T-N-*, T-D-*)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    Queue::fake();
    Storage::fake('s3');
    $this->admin = User::factory()->admin()->create();
});

// ------------------- Publish (T-P-*) ------------------------

// T-P-01
it('T-P-01: publishes a valid Draft via the dedicated endpoint', function () {
    $tender = Tender::factory()->draft()->withBoqAndCriteria()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $response = $this->actingAs($this->admin)->post(route('tenders.publish', $tender));

    $response->assertRedirect();
    $tender->refresh();
    expect($tender->status)->toBe(TenderStatus::Published);
    expect($tender->publish_date)->not->toBeNull();
});

// T-P-02
it('T-P-02: blocks publish when BOQ is missing', function () {
    $tender = Tender::factory()->draft()->withCriteria()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $this->actingAs($this->admin)->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Draft);
});

// T-P-03
it('T-P-03: blocks publish when evaluation criteria are missing', function () {
    $tender = Tender::factory()->draft()->withBoq()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $this->actingAs($this->admin)->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Draft);
});

// T-P-04
it('T-P-04: blocks publishing an already-Published tender (policy)', function () {
    $tender = Tender::factory()->published()->withBoqAndCriteria()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $response = $this->actingAs($this->admin)->post(route('tenders.publish', $tender));

    // Policy: publish requires status === Draft; Published tender => 403 forbidden.
    expect($response->status())->toBeIn([403, 302]);
    expect($tender->fresh()->status)->toBe(TenderStatus::Published);
});

// T-P-05 + T-P-07: terminal statuses
it('blocks publishing terminal or invalid tenders', function (string $status) {
    $tender = Tender::factory()->withBoqAndCriteria()->create(['status' => $status]);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $this->actingAs($this->admin)->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status->value)->toBe($status);
})->with('terminalTenderStatuses');

// T-P-06
it('T-P-06: blocks publish when submission_deadline is already past', function () {
    $tender = Tender::factory()->draft()->withBoqAndCriteria()->create([
        'submission_deadline' => Carbon::now()->subDay(),
    ]);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $this->actingAs($this->admin)->post(route('tenders.publish', $tender));

    expect($tender->fresh()->status)->toBe(TenderStatus::Draft);
});

// ------------------- Addenda (T-A-*) ------------------------

// T-A-01
it('T-A-01: allows addendum on a Published tender', function () {
    $tender = Tender::factory()->published()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $response = $this->actingAs($this->admin)->post(
        route('tenders.addenda.store', $tender),
        [
            'subject' => 'Clarification of Spec',
            'content_en' => 'See updated drawings.',
            'extends_deadline' => false,
        ]
    );

    $response->assertRedirect();
    expect($tender->addenda()->count())->toBe(1);
});

// T-A-03
it('T-A-03: extends deadline via addendum', function () {
    $tender = Tender::factory()->published()->create([
        'submission_deadline' => Carbon::now()->addDays(10),
    ]);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);
    $originalDeadline = $tender->submission_deadline;

    $this->actingAs($this->admin)->post(
        route('tenders.addenda.store', $tender),
        [
            'subject' => 'Deadline Extension',
            'content_en' => 'Extended by one week.',
            'extends_deadline' => true,
            'new_deadline' => Carbon::now()->addDays(17)->format('Y-m-d H:i:s'),
        ]
    )->assertRedirect();

    $tender->refresh();
    expect($tender->submission_deadline->gt($originalDeadline))->toBeTrue();
});

// T-Q-*: Vendor clarifications — separate guard + factory patterns; skipped here
it('T-Q-01: allows a qualified vendor to submit a clarification question')
    ->skip('Vendor guard and qualified-vendor factory not yet wired for test context.');

// ------------------- Close (T-X-*) -------------------------

// T-X-01..05 — skipped: no HTTP close route exists
it('T-X-01: closes a Published tender on admin request')
    ->skip('Close is scheduler-only; no HTTP route. File ETENDER-CLOSE-API if manual close is needed.');

it('T-X-04: cannot close a Draft tender')
    ->skip('Close is scheduler-only; no HTTP route. File ETENDER-CLOSE-API if manual close is needed.');

// T-X-AUTO-01: scheduler-path auto-close via TenderService::closeSubmission()
it('T-X-AUTO-01: closeSubmission() transitions a deadline-past Published tender to SubmissionClosed', function () {
    $tender = Tender::factory()->published()->create([
        'submission_deadline' => Carbon::now()->subHour(),
    ]);

    app(TenderService::class)->closeSubmission($tender);

    expect($tender->fresh()->status)->toBe(TenderStatus::SubmissionClosed);
});

// ------------------- Cancel (T-N-*) ------------------------

// T-N-01
it('T-N-01: cancels a Draft tender with reason', function () {
    $tender = Tender::factory()->draft()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $response = $this->actingAs($this->admin)->post(
        route('tenders.cancel', $tender),
        ['reason' => 'Scope changed']
    );

    $response->assertRedirect();
    expect($tender->fresh()->status)->toBe(TenderStatus::Cancelled);
});

// T-N-02
it('T-N-02: cancels a Published tender with reason', function () {
    $tender = Tender::factory()->published()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $this->actingAs($this->admin)->post(
        route('tenders.cancel', $tender),
        ['reason' => 'Budget withdrawn']
    )->assertRedirect();

    expect($tender->fresh()->status)->toBe(TenderStatus::Cancelled);
});

// T-N-03
it('T-N-03: rejects cancellation without a reason', function () {
    $tender = Tender::factory()->published()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    // Controller validates 'reason' as required.
    $response = $this->actingAs($this->admin)
        ->post(route('tenders.cancel', $tender), []);

    $response->assertSessionHasErrors('reason');
    expect($tender->fresh()->status)->toBe(TenderStatus::Published);
});

// T-N-05
it('T-N-05: audit log records the cancellation', function () {
    $tender = Tender::factory()->published()->create();
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $this->actingAs($this->admin)->post(
        route('tenders.cancel', $tender),
        ['reason' => 'Compliance']
    );

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->admin->id,
        'auditable_type' => Tender::class,
        'auditable_id' => $tender->id,
        'action' => 'updated',
    ]);
});

// ------------------- Delete (T-D-*) — skipped ------------------------
it('T-D-01: deletes a Draft tender')
    ->skip('Delete route not exposed; tenders terminate via cancel.');

it('T-D-02: blocks deletion of a Published tender')
    ->skip('Delete route not exposed; tenders terminate via cancel.');
