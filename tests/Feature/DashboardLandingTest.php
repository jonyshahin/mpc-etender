<?php

use App\Enums\TenderStatus;
use App\Models\Award;
use App\Models\Bid;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function userForDashboard(string ...$slugs): User
{
    $role = Role::factory()->create();

    foreach ($slugs as $slug) {
        $permission = Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace('.', ' ', $slug)), 'module' => explode('.', $slug)[0]],
        );
        $role->permissions()->attach($permission->id);
    }

    return User::factory()->create(['role_id' => $role->id]);
}

/**
 * The state the system is actually in right after mpc:reset-data. A dashboard
 * that only works with data is a dashboard that greets every new deployment
 * with a stack trace.
 */
test('it renders on a completely empty system', function () {
    $this->actingAs(userForDashboard())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.headline.active_tenders', 0)
            ->where('dashboard.headline.awarded_value', 0)
            // Null, not zero: "0% saved" reads as a result rather than an absence.
            ->where('dashboard.headline.savings_rate', null)
            ->where('dashboard.closingSoon', [])
        );
});

test('it counts the headline figures', function () {
    $user = userForDashboard();
    Tender::factory()->count(3)->create(['status' => TenderStatus::Published]);
    Tender::factory()->create(['status' => TenderStatus::Draft]);
    Vendor::factory()->count(2)->create(['prequalification_status' => 'qualified']);
    Vendor::factory()->create(['prequalification_status' => 'pending']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.headline.active_tenders', 3)
            ->where('dashboard.headline.qualified_vendors', 2)
            ->where('dashboard.headline.total_vendors', 3)
        );
});

test('the pipeline covers every status in order, including empty stages', function () {
    Tender::factory()->count(2)->create(['status' => TenderStatus::Published]);

    $this->actingAs(userForDashboard())
        ->get(route('dashboard'))
        ->assertInertia(function (Assert $page) {
            $rows = $page->toArray()['props']['dashboard']['statusDistribution'];

            // Pipeline order, not count order — the sequence is the meaning.
            expect(array_column($rows, 'status'))
                ->toBe(array_map(fn ($c) => $c->value, TenderStatus::cases()));

            $byStatus = array_column($rows, 'count', 'status');
            expect($byStatus['published'])->toBe(2)
                ->and($byStatus['draft'])->toBe(0);
        });
});

test('the trend always spans twelve months, gap-filled', function () {
    $this->actingAs(userForDashboard())
        ->get(route('dashboard'))
        ->assertInertia(function (Assert $page) {
            $trend = $page->toArray()['props']['dashboard']['awardTrend'];

            // A line joining only the months that had activity would imply a
            // slope across the gaps that never happened.
            expect($trend)->toHaveCount(12)
                ->and(collect($trend)->pluck('month')->unique())->toHaveCount(12)
                ->and(collect($trend)->every(fn ($m) => (float) $m['total'] === 0.0))->toBeTrue();
        });
});

test('closing soon lists only published tenders inside the week', function () {
    $soon = Tender::factory()->create([
        'status' => TenderStatus::Published,
        'submission_deadline' => now()->addDays(3),
    ]);
    Tender::factory()->create([
        'status' => TenderStatus::Published,
        'submission_deadline' => now()->addDays(20),
    ]);
    Tender::factory()->create([
        'status' => TenderStatus::Draft,
        'submission_deadline' => now()->addDays(2),
    ]);
    // Already past — belongs on an overdue view, not a countdown.
    Tender::factory()->create([
        'status' => TenderStatus::Published,
        'submission_deadline' => now()->subDay(),
    ]);

    $this->actingAs(userForDashboard())
        ->get(route('dashboard'))
        ->assertInertia(function (Assert $page) use ($soon) {
            $rows = $page->toArray()['props']['dashboard']['closingSoon'];

            expect($rows)->toHaveCount(1)
                ->and($rows[0]['id'])->toBe($soon->id);
        });
});

/**
 * Every queue is a link. Showing one a user cannot open turns the dashboard
 * into a list of dead ends, so each is gated on the permission behind its page.
 */
test('the attention queues follow the permissions the user holds', function () {
    $plain = userForDashboard('vendors.view');

    $this->actingAs($plain)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('dashboard.attention', []));

    $reviewer = userForDashboard('approvals.level1', 'vendors.review_docs');

    $this->actingAs($reviewer)
        ->get(route('dashboard'))
        ->assertInertia(function (Assert $page) {
            $keys = array_column($page->toArray()['props']['dashboard']['attention'], 'key');

            expect($keys)->toContain('approvals', 'vendor_documents')
                ->and($keys)->not->toContain('evaluations');
        });
});

test('the awarded total and savings rate come off real awards', function () {
    $tender = Tender::factory()->create([
        'status' => TenderStatus::Awarded,
        'estimated_value' => 1000,
    ]);
    $vendor = Vendor::factory()->create();
    $bid = Bid::factory()->create(['tender_id' => $tender->id, 'vendor_id' => $vendor->id]);
    Award::factory()->create([
        'tender_id' => $tender->id,
        'bid_id' => $bid->id,
        'award_amount' => 800,
        'awarded_at' => now(),
    ]);

    $this->actingAs(userForDashboard())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.headline.awarded_value', 800)
            ->where('dashboard.headline.savings_rate', 20)
        );
});

test('a guest is sent to login rather than the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect();
});
