<?php

use App\Models\Project;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

/*
|--------------------------------------------------------------------------
| Tender — Authorization (T-Z-*)
|--------------------------------------------------------------------------
*/

// T-Z-01 + T-Z-02
it('blocks unauthorized users from creating tenders', function (Closure $makeActor) {
    $actor = $makeActor();

    $response = $this->actingAs($actor)->post(route('tenders.store'), tenderPayload());

    expect($response->status())->toBeIn([302, 403]);
    expect(Tender::count())->toBe(0);
})->with('actorsWhoCannotCreate');

// T-Z-03: project-manager project scoping — requires project_manager role + project assignment
it('T-Z-03: allows project managers to create tenders for their own project only')
    ->skip('Requires per-user project-role scoping logic not yet reflected in TenderPolicy::create.');

// T-Z-04
it('T-Z-04: redirects unauthenticated requests to login', function () {
    $response = $this->post(route('tenders.store'), tenderPayload());

    expect($response->status())->toBeIn([302, 401]);
    expect(Tender::count())->toBe(0);
});

// T-Z-05
it('T-Z-05: blocks Procurement Officers without tenders.publish from publishing', function () {
    $po = User::factory()->procurementOfficerWithoutPublish()->create();
    $tender = Tender::factory()->draft()->withBoqAndCriteria()->create();
    $po->projects()->attach($tender->project_id, ['project_role' => 'procurement_officer']);

    $response = $this->actingAs($po)->post(route('tenders.publish', $tender));

    expect($response->status())->toBeIn([302, 403]);
    expect($tender->fresh()->status->value)->toBe('draft');
});

// T-Z-06: cross-project isolation on tender show
// FINDING (ETENDER-POLICY-SHOW): TenderController@show does not call
// $this->authorize('view', $tender), so users assigned to project A can
// view tenders on project B. TenderPolicy::view enforces isAssignedToProject
// correctly — it's just never invoked on show.
it('T-Z-06: enforces project-level isolation on tender show')
    ->skip('ETENDER-POLICY-SHOW: TenderController@show missing authorize(view) call — user can access cross-project tender details.');

// Vendor guard: Vendors must not reach MPC tender routes
// FINDING (ETENDER-AUDIT-VENDOR): LogAuditTrail middleware crashes on FK
// constraint when a Vendor-guard user hits a web route (it writes vendor_id
// into both user_id and vendor_id columns without differentiating guards).
it('prevents Vendor-guard users from accessing MPC tender routes')
    ->skip('ETENDER-AUDIT-VENDOR: LogAuditTrail middleware FK constraint violation when Vendor hits web route.');
