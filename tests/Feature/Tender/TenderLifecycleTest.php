<?php

use App\Enums\TenderStatus;
use App\Models\Addendum;
use App\Models\AuditLog;
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

// T-A-03 — Happy path. Updated 2026-04-27 (BUG-26): payload now includes
// new_opening_date alongside new_deadline. Asserts BOTH dates cascade,
// not just the submission deadline.
it('T-A-03: extends deadline via addendum and cascades opening date', function () {
    $tender = Tender::factory()->published()->create([
        'submission_deadline' => Carbon::now()->addDays(10),
        'opening_date' => Carbon::now()->addDays(11),
    ]);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);
    $originalDeadline = $tender->submission_deadline;
    $originalOpening = $tender->opening_date;

    $newDeadline = Carbon::now()->addDays(17);
    $newOpening = Carbon::now()->addDays(18); // 24h after new deadline = buffer min

    $this->actingAs($this->admin)->post(
        route('tenders.addenda.store', $tender),
        [
            'subject' => 'Deadline Extension',
            'content_en' => 'Extended by one week.',
            'extends_deadline' => true,
            'new_deadline' => $newDeadline->format('Y-m-d H:i:s'),
            'new_opening_date' => $newOpening->format('Y-m-d H:i:s'),
        ]
    )->assertRedirect();

    $tender->refresh();
    expect($tender->submission_deadline->gt($originalDeadline))->toBeTrue();
    expect($tender->opening_date->gt($originalOpening))->toBeTrue();
    expect($tender->opening_date->gt($tender->submission_deadline))->toBeTrue();
});

// T-A-04 — Validation: missing new_opening_date when extending deadline.
it('T-A-04: rejects deadline extension without new opening date', function () {
    $tender = Tender::factory()->published()->create([
        'submission_deadline' => Carbon::now()->addDays(10),
        'opening_date' => Carbon::now()->addDays(11),
    ]);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $this->actingAs($this->admin)->post(
        route('tenders.addenda.store', $tender),
        [
            'subject' => 'Deadline Extension',
            'content_en' => 'Extended.',
            'extends_deadline' => true,
            'new_deadline' => Carbon::now()->addDays(17)->format('Y-m-d H:i:s'),
            // new_opening_date deliberately omitted — should fail required_if.
        ]
    )->assertSessionHasErrors('new_opening_date');

    expect($tender->fresh()->addenda()->count())->toBe(0);
});

// T-A-05 — Validation: opening date must be after new deadline.
it('T-A-05: rejects deadline extension when new opening date is before new deadline', function () {
    $tender = Tender::factory()->published()->create([
        'submission_deadline' => Carbon::now()->addDays(10),
        'opening_date' => Carbon::now()->addDays(11),
    ]);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $this->actingAs($this->admin)->post(
        route('tenders.addenda.store', $tender),
        [
            'subject' => 'Deadline Extension',
            'content_en' => 'Extended.',
            'extends_deadline' => true,
            'new_deadline' => Carbon::now()->addDays(17)->format('Y-m-d H:i:s'),
            'new_opening_date' => Carbon::now()->addDays(15)->format('Y-m-d H:i:s'),
        ]
    )->assertSessionHasErrors('new_opening_date');

    expect($tender->fresh()->addenda()->count())->toBe(0);
});

// T-A-06 — Validation: buffer enforced (default 24h between deadline and opening).
it('T-A-06: rejects deadline extension when buffer between deadline and opening is too small', function () {
    $tender = Tender::factory()->published()->create([
        'submission_deadline' => Carbon::now()->addDays(10),
        'opening_date' => Carbon::now()->addDays(11),
    ]);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $newDeadline = Carbon::now()->addDays(17);
    $tooSoon = $newDeadline->copy()->addHour(); // 1h gap, default buffer is 24h

    $this->actingAs($this->admin)->post(
        route('tenders.addenda.store', $tender),
        [
            'subject' => 'Deadline Extension',
            'content_en' => 'Extended.',
            'extends_deadline' => true,
            'new_deadline' => $newDeadline->format('Y-m-d H:i:s'),
            'new_opening_date' => $tooSoon->format('Y-m-d H:i:s'),
        ]
    )->assertSessionHasErrors('new_opening_date');

    expect($tender->fresh()->addenda()->count())->toBe(0);
});

// T-A-07 — Audit log: cascading addendum writes one audit row capturing
// both old and new values for submission_deadline AND opening_date.
it('T-A-07: writes audit log entries for both submission_deadline and opening_date when cascading', function () {
    $tender = Tender::factory()->published()->create([
        'submission_deadline' => Carbon::now()->addDays(10),
        'opening_date' => Carbon::now()->addDays(11),
    ]);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);

    $newDeadline = Carbon::now()->addDays(17);
    $newOpening = Carbon::now()->addDays(18);

    $this->actingAs($this->admin)->post(
        route('tenders.addenda.store', $tender),
        [
            'subject' => 'Deadline Extension',
            'content_en' => 'Extended.',
            'extends_deadline' => true,
            'new_deadline' => $newDeadline->format('Y-m-d H:i:s'),
            'new_opening_date' => $newOpening->format('Y-m-d H:i:s'),
        ]
    )->assertRedirect();

    $entry = AuditLog::query()
        ->where('auditable_type', Tender::class)
        ->where('auditable_id', $tender->id)
        ->where('action', 'addendum_extends_deadline')
        ->latest('created_at')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->old_values)->toHaveKeys(['submission_deadline', 'opening_date']);
    expect($entry->new_values)->toHaveKeys(['submission_deadline', 'opening_date']);
});

// T-A-08 — Transaction rollback: if the addendum insert blows up after the
// tender update is staged, the tender's dates must NOT be persisted.
// Forces failure via an oversize subject (rejected at validation, but the
// real test is that an exception thrown mid-transaction unwinds the update).
it('T-A-08: rolls back tender date changes if addendum creation fails', function () {
    $tender = Tender::factory()->published()->create([
        'submission_deadline' => Carbon::now()->addDays(10),
        'opening_date' => Carbon::now()->addDays(11),
    ]);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);
    $originalDeadline = $tender->submission_deadline->toIso8601String();
    $originalOpening = $tender->opening_date->toIso8601String();

    // Simulate a DB write failure inside the transaction by using an
    // addendum_number that conflicts with a manually-inserted duplicate.
    // We can't easily force that here without race conditions, so instead
    // we drop the addenda table column the controller writes to mid-test.
    // Simpler: use a model event listener that throws on Addendum::creating.
    Addendum::creating(function () {
        throw new RuntimeException('Simulated addendum insert failure');
    });

    try {
        $this->actingAs($this->admin)->post(
            route('tenders.addenda.store', $tender),
            [
                'subject' => 'Deadline Extension',
                'content_en' => 'Extended.',
                'extends_deadline' => true,
                'new_deadline' => Carbon::now()->addDays(17)->format('Y-m-d H:i:s'),
                'new_opening_date' => Carbon::now()->addDays(18)->format('Y-m-d H:i:s'),
            ]
        );
    } finally {
        // Tear down the listener so subsequent tests aren't affected.
        Addendum::flushEventListeners();
    }

    $tender->refresh();
    expect($tender->submission_deadline->toIso8601String())->toBe($originalDeadline);
    expect($tender->opening_date->toIso8601String())->toBe($originalOpening);
    expect($tender->addenda()->count())->toBe(0);
});

// T-A-09 — No-cascade case: addendum without deadline extension leaves
// both submission_deadline AND opening_date untouched.
it('T-A-09: does not modify tender dates when extends_deadline is false', function () {
    $tender = Tender::factory()->published()->create([
        'submission_deadline' => Carbon::now()->addDays(10),
        'opening_date' => Carbon::now()->addDays(11),
    ]);
    $this->admin->projects()->attach($tender->project_id, ['project_role' => 'admin']);
    $originalDeadline = $tender->submission_deadline->toIso8601String();
    $originalOpening = $tender->opening_date->toIso8601String();

    $this->actingAs($this->admin)->post(
        route('tenders.addenda.store', $tender),
        [
            'subject' => 'Clarification only',
            'content_en' => 'See attached drawings.',
            'extends_deadline' => false,
        ]
    )->assertRedirect();

    $tender->refresh();
    expect($tender->submission_deadline->toIso8601String())->toBe($originalDeadline);
    expect($tender->opening_date->toIso8601String())->toBe($originalOpening);
    expect($tender->addenda()->count())->toBe(1);
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
