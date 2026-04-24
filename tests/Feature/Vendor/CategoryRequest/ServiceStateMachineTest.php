<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategoryRequest;
use App\Notifications\VendorCategoryRequestApproved;
use App\Notifications\VendorCategoryRequestRejected;
use App\Services\VendorCategoryRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->service = app(VendorCategoryRequestService::class);
    $this->vendor = Vendor::factory()->create();
    $this->reviewer = User::factory()->create();
});

function fakeEvidence(string $name = 'license.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 500, 'application/pdf');
}

// 1 — happy-path submit: delta, evidence, audit
test('submit happy path creates request + items + evidence + audit row', function () {
    $cats = Category::factory()->count(3)->create();
    $existing = $cats->first();
    $this->vendor->categories()->syncWithoutDetaching([$existing->id]);

    $req = $this->service->submit(
        $this->vendor,
        'We purchased new steel-fabrication equipment and want to bid on civil+steel tenders.',
        addCategoryIds: [$cats[1]->id, $cats[2]->id],
        removeCategoryIds: [$existing->id],
        evidenceFiles: [fakeEvidence('license.pdf'), fakeEvidence('equipment.pdf')],
    );

    expect($req->status)->toBe('pending');
    expect($req->items()->where('operation', 'add')->count())->toBe(2);
    expect($req->items()->where('operation', 'remove')->count())->toBe(1);
    expect($req->evidence()->count())->toBe(2);

    // Uploads actually land on the fake disk
    expect(Storage::disk('s3')->allFiles())->toHaveCount(2);

    // Audit row
    expect(AuditLog::where('action', 'vendor_category_request_submitted')
        ->where('auditable_id', $req->id)->exists())->toBeTrue();
});

// 2 — empty delta
test('submit with empty delta throws ValidationException', function () {
    expect(fn () => $this->service->submit(
        $this->vendor,
        'some justification',
        addCategoryIds: [],
        removeCategoryIds: [],
        evidenceFiles: [fakeEvidence()],
    ))->toThrow(ValidationException::class);

    expect(VendorCategoryRequest::count())->toBe(0);
});

// 3 — all adds already owned → effective delta becomes empty → throws
test('submit where adds are already approved yields no-net-change and throws', function () {
    $cat = Category::factory()->create();
    $this->vendor->categories()->syncWithoutDetaching([$cat->id]);

    expect(fn () => $this->service->submit(
        $this->vendor,
        'requesting what I already have',
        addCategoryIds: [$cat->id],
        removeCategoryIds: [],
        evidenceFiles: [fakeEvidence()],
    ))->toThrow(ValidationException::class);

    expect(VendorCategoryRequest::count())->toBe(0);
});

// 4 — one-at-a-time enforcement
test('submit while an open request exists throws', function () {
    $cats = Category::factory()->count(2)->create();
    $this->service->submit(
        $this->vendor,
        'first request',
        addCategoryIds: [$cats[0]->id],
        removeCategoryIds: [],
        evidenceFiles: [fakeEvidence()],
    );

    expect(fn () => $this->service->submit(
        $this->vendor,
        'second request while first is pending',
        addCategoryIds: [$cats[1]->id],
        removeCategoryIds: [],
        evidenceFiles: [fakeEvidence()],
    ))->toThrow(ValidationException::class);

    expect(VendorCategoryRequest::count())->toBe(1);
});

// 5 — approve mutates pivot, writes audit, fires notification
test('approve mutates pivot + fires notification + writes audit', function () {
    Notification::fake();

    $keep = Category::factory()->create();
    $this->vendor->categories()->syncWithoutDetaching([$keep->id]);

    $addCat = Category::factory()->create();
    $removeCat = $keep;

    $req = $this->service->submit(
        $this->vendor,
        'expanding into MEP',
        addCategoryIds: [$addCat->id],
        removeCategoryIds: [$removeCat->id],
        evidenceFiles: [fakeEvidence()],
    );

    $approved = $this->service->approve($req, $this->reviewer, 'Looks good.');

    expect($approved->status)->toBe('approved');
    expect($approved->reviewed_by)->toBe($this->reviewer->id);
    expect($approved->reviewer_comments)->toBe('Looks good.');

    // Pivot reflects the delta
    $currentIds = $this->vendor->fresh()->categories()->pluck('categories.id')->all();
    expect($currentIds)->toContain($addCat->id);
    expect($currentIds)->not->toContain($removeCat->id);

    Notification::assertSentTo($this->vendor, VendorCategoryRequestApproved::class);

    expect(AuditLog::where('action', 'vendor_category_request_approved')
        ->where('auditable_id', $req->id)
        ->where('user_id', $this->reviewer->id)
        ->exists())->toBeTrue();
});

// 6 — approving a terminal request throws
test('approve on already-approved / rejected / withdrawn request throws', function () {
    Notification::fake();
    $cat = Category::factory()->create();
    $req = $this->service->submit(
        $this->vendor,
        'baseline',
        addCategoryIds: [$cat->id],
        removeCategoryIds: [],
        evidenceFiles: [fakeEvidence()],
    );
    $this->service->approve($req, $this->reviewer);

    // Second approve call must throw
    expect(fn () => $this->service->approve($req->fresh(), $this->reviewer))
        ->toThrow(ValidationException::class);
});

// 7 — reject: no pivot mutation, notifies, requires comments
test('reject leaves pivot unchanged + fires notification + requires non-empty comments', function () {
    Notification::fake();

    $keep = Category::factory()->create();
    $this->vendor->categories()->syncWithoutDetaching([$keep->id]);
    $originalIds = $this->vendor->fresh()->categories()->pluck('categories.id')->all();

    $addCat = Category::factory()->create();
    $req = $this->service->submit(
        $this->vendor,
        'want more scope',
        addCategoryIds: [$addCat->id],
        removeCategoryIds: [],
        evidenceFiles: [fakeEvidence()],
    );

    // Empty comments rejection → throws
    expect(fn () => $this->service->reject($req, $this->reviewer, '   '))
        ->toThrow(ValidationException::class);

    // Valid rejection
    $rejected = $this->service->reject($req->fresh(), $this->reviewer, 'Evidence insufficient — please re-submit with ISO cert.');

    expect($rejected->status)->toBe('rejected');
    expect($rejected->reviewer_comments)->toContain('ISO cert');

    // Pivot unchanged
    $currentIds = $this->vendor->fresh()->categories()->pluck('categories.id')->sort()->values()->all();
    expect($currentIds)->toBe(collect($originalIds)->sort()->values()->all());

    Notification::assertSentTo($this->vendor, VendorCategoryRequestRejected::class);
    expect(AuditLog::where('action', 'vendor_category_request_rejected')
        ->where('auditable_id', $req->id)->exists())->toBeTrue();
});

// 8 — withdraw: only owner, only from open states
test('withdraw: owner-only and only from open states; optional reason captured', function () {
    $cat = Category::factory()->create();
    $req = $this->service->submit(
        $this->vendor,
        'justification',
        addCategoryIds: [$cat->id],
        removeCategoryIds: [],
        evidenceFiles: [fakeEvidence()],
    );

    // Non-owner cannot withdraw
    $other = Vendor::factory()->create();
    expect(fn () => $this->service->withdraw($req, $other))
        ->toThrow(HttpException::class);

    // Owner withdraws with reason
    $withdrawn = $this->service->withdraw($req, $this->vendor, 'Missing ISO cert — will resubmit later.');
    expect($withdrawn->status)->toBe('withdrawn');
    expect($withdrawn->withdraw_reason)->toContain('ISO cert');

    // Cannot withdraw again (terminal state)
    expect(fn () => $this->service->withdraw($withdrawn->fresh(), $this->vendor))
        ->toThrow(ValidationException::class);

    // Cannot withdraw an approved request
    Notification::fake();
    $cat2 = Category::factory()->create();
    $req2 = $this->service->submit(
        $this->vendor,
        'another',
        addCategoryIds: [$cat2->id],
        removeCategoryIds: [],
        evidenceFiles: [fakeEvidence()],
    );
    $this->service->approve($req2, $this->reviewer);

    expect(fn () => $this->service->withdraw($req2->fresh(), $this->vendor))
        ->toThrow(ValidationException::class);
});
