<?php

/**
 * Defects found auditing the vendor portal screens (dashboard, profile,
 * documents, categories, category requests, notifications).
 *
 * Each test here was written against the pre-rebuild code and failed. They are
 * kept as regression guards rather than folded into the per-screen tests
 * because what they pin down is mostly *absence* — a column that must not
 * reach the browser, a log row that must exist — which is exactly the kind of
 * thing a later refactor reintroduces quietly.
 */

use App\Enums\VendorDocStatus;
use App\Models\Category;
use App\Models\DocumentAccessLog;
use App\Models\Notification;
use App\Models\Vendor;
use App\Models\VendorCategoryRequest;
use App\Models\VendorCategoryRequestEvidence;
use App\Models\VendorDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->vendor = Vendor::factory()->qualified()->create(['is_active' => true]);
});

// ── Inertia payloads ──

test('profile page does not ship the vendor internal review record', function () {
    // ProfileController passed $request->user('vendor') whole. $hidden covers
    // the password, but nothing else — so the admin's rejection note, the id of
    // the MPC user who qualified them, and the account's active/must-change
    // flags all rode along on a page that renders twelve editable fields.
    $this->vendor->forceFill([
        'rejection_reason' => 'Internal note: financials looked thin, revisit Q3.',
    ])->save();

    $this->actingAs($this->vendor, 'vendor')
        ->get(route('vendor.profile.edit'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $vendor = $page->toArray()['props']['vendor'];

            foreach (['rejection_reason', 'qualified_by', 'is_active', 'must_change_password', 'last_login_at'] as $leaked) {
                expect($vendor)->not->toHaveKey($leaked);
            }
        });
});

test('documents page does not ship the raw storage key', function () {
    // file_path is the S3 object key. The page never rendered it — there was no
    // download route to use it with — so it was pure exhaust.
    VendorDocument::factory()->for($this->vendor)->create();

    $this->actingAs($this->vendor, 'vendor')
        ->get(route('vendor.documents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => expect($page->toArray()['props']['documents'][0])
            ->not->toHaveKey('file_path'));
});

test('notification list does not ship internal routing columns', function () {
    // vendorIndex paginated whole models: notifiable_type is a fully-qualified
    // PHP class name, and user_id/vendor_id are internal addressing the page
    // has no use for.
    Notification::factory()->create(['vendor_id' => $this->vendor->id]);

    $this->actingAs($this->vendor, 'vendor')
        ->get(route('vendor.notifications.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $row = $page->toArray()['props']['notifications']['data'][0];

            foreach (['notifiable_type', 'notifiable_id', 'user_id', 'vendor_id'] as $leaked) {
                expect($row)->not->toHaveKey($leaked);
            }
        });
});

// ── Document access logging (CLAUDE.md: all document access logged) ──

test('a vendor can download their own document and the access is logged', function () {
    // There was no download route at all: a vendor could upload a document and
    // never retrieve it. Adding one brings it under the logging rule.
    $document = VendorDocument::factory()->for($this->vendor)->create();

    $this->actingAs($this->vendor, 'vendor')
        ->get(route('vendor.documents.download', $document))
        ->assertRedirect();

    expect(DocumentAccessLog::where('document_id', $document->id)
        ->where('vendor_id', $this->vendor->id)
        ->where('action', 'downloaded')
        ->exists())->toBeTrue();
});

test('a vendor cannot download another vendors document', function () {
    $document = VendorDocument::factory()->for(Vendor::factory()->create())->create();

    $this->actingAs($this->vendor, 'vendor')
        ->get(route('vendor.documents.download', $document))
        ->assertForbidden();

    expect(DocumentAccessLog::count())->toBe(0);
});

test('category request evidence download is logged', function () {
    // downloadEvidence handed out a presigned S3 URL and wrote nothing.
    // TenderBrowseController::show logs its document views; this did not.
    $request = VendorCategoryRequest::create([
        'vendor_id' => $this->vendor->id,
        'justification' => 'Justification long enough to pass validation.',
        'status' => 'pending',
    ]);
    $evidence = VendorCategoryRequestEvidence::create([
        'request_id' => $request->id,
        'path' => 'vendor-category-requests/'.$request->id.'/proof.pdf',
        'original_name' => 'proof.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
        'uploaded_by_vendor_id' => $this->vendor->id,
    ]);

    $this->actingAs($this->vendor, 'vendor')
        ->get(route('vendor.category-requests.evidence.download', [$request, $evidence]))
        ->assertRedirect();

    expect(DocumentAccessLog::where('document_id', $evidence->id)
        ->where('vendor_id', $this->vendor->id)
        ->where('action', 'downloaded')
        ->exists())->toBeTrue();
});

// ── Sorting the list actually offers ──

test('category request list honours a sort the table offers', function () {
    // The React table marked created_at, status and reviewed_at sortable and
    // navigated with ?sort=&direction=. The controller hardcoded
    // orderByDesc('created_at') and ignored both, so clicking a header
    // reloaded the same page in the same order.
    foreach (['withdrawn', 'approved', 'pending'] as $index => $status) {
        VendorCategoryRequest::create([
            'vendor_id' => $this->vendor->id,
            'justification' => "Justification number {$index}, long enough to be valid.",
            'status' => $status,
        ]);
    }

    $this->actingAs($this->vendor, 'vendor')
        ->get(route('vendor.category-requests.index', ['sort' => 'status', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $statuses = array_column($page->toArray()['props']['requests']['data'], 'status');

            expect($statuses)->toBe(['approved', 'pending', 'withdrawn']);
        });
});

test('an unknown sort column falls back instead of reaching the query grammar', function () {
    // Guards the whitelist added alongside the sorting above: orderBy() hands
    // the column straight to the grammar, so an unchecked ?sort= is a 500.
    VendorCategoryRequest::create([
        'vendor_id' => $this->vendor->id,
        'justification' => 'Justification long enough to pass validation.',
        'status' => 'pending',
    ]);

    $this->actingAs($this->vendor, 'vendor')
        ->get(route('vendor.category-requests.index', ['sort' => 'vendor_id); drop table vendors;--']))
        ->assertOk();
});

// ── Dashboard ──

test('a rejected document that has lapsed is not reported as an expired credential', function () {
    // Both expiry queries filtered on expiry_date alone. A document MPC had
    // already rejected therefore reappeared as an expired credential on the
    // vendor's file, prompting a renew-and-reupload that fixes nothing — the
    // document was never on file to begin with.
    VendorDocument::factory()->for($this->vendor)->create([
        'status' => VendorDocStatus::Rejected,
        'expiry_date' => now()->subMonth(),
    ]);
    VendorDocument::factory()->for($this->vendor)->create([
        'status' => VendorDocStatus::Rejected,
        'expiry_date' => now()->addDays(10),
    ]);

    $this->actingAs($this->vendor, 'vendor')
        ->get(route('vendor.dashboard'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            expect($props['expiredDocuments'])->toBeEmpty()
                ->and($props['documentWarnings'])->toBeEmpty();
        });
});

test('mark all notifications read is available to vendors', function () {
    // MPC users had markAllRead; vendors had to click every notification
    // individually. Same model, same inbox, one guard short.
    Notification::factory()->count(3)->create(['vendor_id' => $this->vendor->id]);
    $otherVendorsUnread = Notification::factory()->create(['vendor_id' => Vendor::factory()->create()->id]);

    $this->actingAs($this->vendor, 'vendor')
        ->post(route('vendor.notifications.read-all'))
        ->assertRedirect();

    expect(Notification::where('vendor_id', $this->vendor->id)->whereNull('read_at')->count())->toBe(0)
        ->and($otherVendorsUnread->fresh()->read_at)->toBeNull();
});

test('categories page counts only categories that are still active', function () {
    // The approved-category tally the page leads with came from the raw pivot,
    // while the tree beside it renders Category::active(). A category MPC
    // retired left the vendor reading "5 approved" above four rows.
    $live = Category::factory()->create(['is_active' => true]);
    $retired = Category::factory()->create(['is_active' => false]);
    $this->vendor->categories()->attach([$live->id, $retired->id]);

    $this->actingAs($this->vendor, 'vendor')
        ->get(route('vendor.categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => expect($page->toArray()['props']['selectedCategoryIds'])
            ->toBe([$live->id]));
});
