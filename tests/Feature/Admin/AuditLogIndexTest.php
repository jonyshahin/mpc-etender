<?php

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/** Helper names in Pest files are global — hence the suffix. */
function adminForAuditLogList(): User
{
    // The route group gates on the role slug alone (role:admin,super_admin);
    // there is no audit-specific permission. firstOrCreate keeps a second call
    // in the same test off the unique index on roles.slug.
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'is_system' => true]);

    $permission = Permission::firstOrCreate(
        ['slug' => 'audit.view'],
        ['name' => 'Audit View', 'module' => 'audit'],
    );
    $role->permissions()->syncWithoutDetaching([$permission->id]);

    return User::factory()->create(['role_id' => $role->id]);
}

/** @return array<string, mixed> */
function auditProps(array $query = []): array
{
    $page = test()->actingAs(adminForAuditLogList())
        ->get(route('admin.audit-logs.index', $query))
        ->assertOk();

    $props = [];
    $page->assertInertia(function (Assert $inertia) use (&$props) {
        $props = $inertia->toArray()['props'];
    });

    return $props;
}

/**
 * The bug this page was rebuilt around.
 *
 * Storage is UTC, display and input are Asia/Baghdad. The filter compared the
 * raw 'YYYY-MM-DD' against the UTC column, so the window sat three hours to
 * the left of the one the user picked: it dropped the first three hours of the
 * `from` day and swept in the first three of the day after `to`.
 */
test('the date window is the project zone day, not the UTC one', function () {
    config(['mpc.timezone' => 'Asia/Baghdad']);

    // 00:30 on 1 Sep in Baghdad. The old query excluded it.
    $justAfterMidnight = AuditLog::factory()->create(['created_at' => '2026-08-31 21:30:00']);
    // 15:00 on 1 Sep in Baghdad, unambiguous either way.
    $midday = AuditLog::factory()->create(['created_at' => '2026-09-01 12:00:00']);
    // 00:30 on 2 Sep in Baghdad. The old query included it.
    AuditLog::factory()->create(['created_at' => '2026-09-01 21:30:00']);
    // 23:00 on 31 Aug in Baghdad.
    AuditLog::factory()->create(['created_at' => '2026-08-31 20:00:00']);

    $props = auditProps(['from' => '2026-09-01', 'to' => '2026-09-01']);

    expect(collect($props['logs']['data'])->pluck('id')->sort()->values()->all())
        ->toBe(collect([$justAfterMidnight->id, $midday->id])->sort()->values()->all());
});

test('each bound works on its own', function (array $query, int $expected) {
    config(['mpc.timezone' => 'Asia/Baghdad']);

    AuditLog::factory()->create(['created_at' => '2026-08-31 21:30:00']); // 1 Sep 00:30
    AuditLog::factory()->create(['created_at' => '2026-09-01 21:30:00']); // 2 Sep 00:30

    expect(auditProps($query)['logs']['data'])->toHaveCount($expected);
})->with([
    'from only' => [['from' => '2026-09-02'], 1],
    'to only' => [['to' => '2026-09-01'], 1],
    'window covering both' => [['from' => '2026-09-01', 'to' => '2026-09-02'], 2],
    'window covering neither' => [['from' => '2026-09-03'], 0],
]);

/**
 * createFromFormat rolls 2026-13-45 forward into 2027 rather than refusing it,
 * so a malformed value would have filtered on a window nobody asked for.
 */
test('an unusable date is ignored rather than applied or echoed back', function (string $value) {
    AuditLog::factory()->count(3)->create();

    $props = auditProps(['from' => $value]);

    expect($props['logs']['data'])->toHaveCount(3)
        ->and($props['filters']['from'])->toBeNull();
})->with([
    'impossible month and day' => ['2026-13-45'],
    'not a date at all' => ['yesterday'],
    'wrong shape' => ['01/09/2026'],
    'injection attempt' => ["2026-09-01' or '1'='1"],
]);

/**
 * DataTable merges this set into every sort request, so an incomplete echo
 * sorts with the filters wiped and — comparing against a sort that is always
 * undefined — can never reach descending. The page passed `filters` through,
 * but the controller built it with $request->only(), which carries no sort or
 * direction at all and is an empty array on an unfiltered request.
 */
test('the filters it echoes back are complete enough to sort with', function () {
    $user = User::factory()->create();

    $props = auditProps([
        'search' => '10.0.0.1',
        'user_id' => $user->id,
        'action' => 'updated',
        'entity_type' => Tender::class,
        'from' => '2026-09-01',
        'to' => '2026-09-02',
    ]);

    expect($props['filters'])->toBe([
        'search' => '10.0.0.1',
        'user_id' => $user->id,
        'action' => 'updated',
        'entity_type' => Tender::class,
        'from' => '2026-09-01',
        'to' => '2026-09-02',
        'sort' => 'created_at',
        'direction' => 'desc',
    ]);
});

/**
 * This list never took a sort column from the request, so the `?sort=` 500 the
 * tender, vendor and project lists carried could not happen here. The sortable
 * headers arrive with the whitelist rather than after it.
 */
test('an unusable sort or direction falls back instead of erroring', function (array $query, string $sort, string $direction) {
    $props = auditProps($query);

    expect($props['filters']['sort'])->toBe($sort)
        ->and($props['filters']['direction'])->toBe($direction);
})->with([
    'unknown column' => [['sort' => 'not_a_column'], 'created_at', 'desc'],
    'injection attempt' => [['sort' => 'id); drop table audit_logs; --'], 'created_at', 'desc'],
    'a column that exists but is not offered' => [['sort' => 'ip_address'], 'created_at', 'desc'],
    'bad direction' => [['direction' => 'upwards'], 'created_at', 'desc'],
    'valid pair' => [['sort' => 'action', 'direction' => 'asc'], 'action', 'asc'],
]);

/**
 * old_values/new_values are free-form JSON and reached the browser verbatim.
 * Bid is the cautionary case: no $hidden plus a get-mutator that decrypted on
 * access meant serialising the model leaked sealed pricing.
 */
test('sensitive payload values are replaced before they reach the page', function () {
    AuditLog::factory()->create([
        'auditable_type' => Bid::class,
        'new_values' => [
            'status' => 'opened',
            'total_amount' => '4250000.00',
            'encrypted_pricing_data' => 'eyJib3EiOlt7InVuaXQiOjEyNX1dfQ==',
            'must_change_password' => true,
            'meta' => ['api_key' => 'sk-live-abc123', 'rows' => 4],
            'pricing' => ['item_1' => 1000, 'item_2' => 2000],
        ],
    ]);

    $values = auditProps()['logs']['data'][0]['new_values'];

    expect($values['status'])->toBe('opened')
        ->and($values['rows'] ?? null)->toBeNull()
        ->and($values['total_amount'])->toBe('__redacted__')
        ->and($values['encrypted_pricing_data'])->toBe('__redacted__')
        ->and($values['meta']['api_key'])->toBe('__redacted__')
        // Not under a sensitive key, and a sibling being one does not hide it.
        ->and($values['meta']['rows'])->toBe(4)
        // A sensitive key holding an array hides the whole subtree.
        ->and($values['pricing'])->toBe(['item_1' => '__redacted__', 'item_2' => '__redacted__'])
        // A boolean cannot be the secret, so it stays readable.
        ->and($values['must_change_password'])->toBeTrue();
});

test('nothing but the fields the page renders is serialised', function () {
    AuditLog::factory()->create(['user_agent' => 'Mozilla/5.0 (secret build 1234)']);

    $row = auditProps()['logs']['data'][0];

    expect(array_keys($row))->toEqualCanonicalizing([
        'id', 'created_at', 'action', 'entity_type', 'entity_id', 'ip_address',
        'actor', 'old_values', 'new_values',
    ]);
});

/**
 * A vendor acting on their own account sets vendor_id and leaves user_id null
 * by design. The page read user_id alone, so every one of those events was
 * attributed to "System".
 */
test('the actor is named whether it is a user, a vendor or neither', function () {
    $user = User::factory()->create(['name' => 'Layla Hassan']);
    $vendor = Vendor::factory()->create(['company_name' => 'Baghdad Steel Works']);

    AuditLog::factory()->create(['user_id' => $user->id, 'created_at' => '2026-09-01 10:00:00']);
    AuditLog::factory()->create(['vendor_id' => $vendor->id, 'created_at' => '2026-09-01 09:00:00']);
    AuditLog::factory()->create(['created_at' => '2026-09-01 08:00:00']);

    $actors = collect(auditProps()['logs']['data'])->pluck('actor')->all();

    expect($actors)->toBe([
        ['type' => 'user', 'name' => 'Layla Hassan'],
        ['type' => 'vendor', 'name' => 'Baghdad Steel Works'],
        ['type' => 'system', 'name' => null],
    ]);
});

/**
 * The filter offered a literal eight-item list. "exported" and "imported" are
 * not in it because nothing in the app has ever written them; everything the
 * app does write that the list omitted is.
 */
test('the action options are the actions that exist, not a literal list', function () {
    AuditLog::factory()->count(2)->create(['action' => 'vendor_category_request_submitted']);
    AuditLog::factory()->create(['action' => 'post']);
    AuditLog::factory()->create(['action' => 'login']);

    $options = auditProps()['actionOptions'];

    expect(collect($options)->pluck('value')->all())
        // Commonest first, then alphabetical.
        ->toBe(['vendor_category_request_submitted', 'login', 'post'])
        ->and($options[0]['count'])->toBe(2)
        ->and(collect($options)->pluck('value'))->not->toContain('exported')
        ->and(collect($options)->pluck('value'))->not->toContain('imported');
});

test('the entity options carry the stored type, which is what the filter matches', function () {
    AuditLog::factory()->count(3)->create(['auditable_type' => Tender::class]);
    AuditLog::factory()->create(['auditable_type' => 'http_request']);

    $props = auditProps();

    expect(collect($props['entityOptions'])->pluck('value')->all())
        ->toBe([Tender::class, 'http_request']);

    // The old control was a free-text box placeholdered "e.g. Tender" against
    // an exact match on 'App\Models\Tender' — it could never match anything.
    expect(auditProps(['entity_type' => 'Tender'])['logs']['data'])->toHaveCount(0)
        ->and(auditProps(['entity_type' => Tender::class])['logs']['data'])->toHaveCount(3);
});

test('the actor options name the staff who appear in the window', function () {
    $layla = User::factory()->create(['name' => 'Layla Hassan']);
    $omar = User::factory()->create(['name' => 'Omar Kareem']);
    User::factory()->create(['name' => 'Never Acted']);

    AuditLog::factory()->count(2)->create(['user_id' => $omar->id]);
    AuditLog::factory()->create(['user_id' => $layla->id]);
    AuditLog::factory()->create(['vendor_id' => Vendor::factory()->create()->id]);

    $options = auditProps()['actorOptions'];

    expect($options)->toBe([
        ['value' => $layla->id, 'label' => 'Layla Hassan', 'count' => 1],
        ['value' => $omar->id, 'label' => 'Omar Kareem', 'count' => 2],
    ]);
});

/**
 * A facet counted on a scope that already includes its own selection collapses
 * to that selection the moment you pick from it, which makes the other options
 * look empty and unpickable.
 */
test('a facet keeps its full counts while its own filter is applied', function () {
    AuditLog::factory()->count(2)->create(['auditable_type' => Tender::class, 'action' => 'updated']);
    AuditLog::factory()->create(['auditable_type' => 'http_request', 'action' => 'post']);

    $props = auditProps(['entity_type' => Tender::class]);

    expect($props['logs']['data'])->toHaveCount(2)
        // The pills still offer http_request, with its count.
        ->and(collect($props['entityOptions'])->firstWhere('value', 'http_request')['count'])->toBe(1)
        // The action list, however, narrows to the chosen entity.
        ->and(collect($props['actionOptions'])->pluck('value')->all())->toBe(['updated']);
});

test('the search matches the user, the vendor, the IP and the record', function (string $term) {
    $user = User::factory()->create(['name' => 'Layla Hassan']);
    $vendor = Vendor::factory()->create(['company_name' => 'Baghdad Steel Works']);

    AuditLog::factory()->create([
        'user_id' => $user->id,
        'ip_address' => '10.20.30.40',
        'auditable_id' => 'admin.projects.index',
    ]);
    AuditLog::factory()->create(['vendor_id' => $vendor->id, 'ip_address' => '192.168.1.1']);
    AuditLog::factory()->create(['ip_address' => '172.16.0.9', 'auditable_id' => 'vendor.dashboard']);

    expect(auditProps(['search' => $term])['logs']['data'])->toHaveCount(1);
})->with([
    'user name' => ['Layla'],
    'vendor name' => ['Baghdad Steel'],
    'ip address' => ['10.20.30.40'],
    'record id' => ['admin.projects'],
]);

test('the summary describes the window rather than the categorical filters', function () {
    config(['mpc.timezone' => 'Asia/Baghdad']);

    $user = User::factory()->create();

    AuditLog::factory()->count(2)->create([
        'user_id' => $user->id,
        'auditable_type' => Tender::class,
        'created_at' => '2026-09-01 12:00:00',
    ]);
    AuditLog::factory()->create([
        'auditable_type' => 'http_request',
        'created_at' => '2026-09-01 13:00:00',
        'old_values' => ['status' => 'draft'],
        'new_values' => ['status' => 'published'],
    ]);

    $props = auditProps([
        'from' => '2026-09-01',
        'to' => '2026-09-01',
        'entity_type' => Tender::class,
    ]);

    expect($props['logs']['data'])->toHaveCount(2)
        // All three rows sit in the window, entity pill or not.
        ->and($props['summary']['total'])->toBe(3)
        ->and($props['summary']['actors'])->toBe(1)
        ->and($props['summary']['changes'])->toBe(1)
        // Nothing was written today; the window is in 2026-09.
        ->and($props['summary']['today'])->toBe(0);
});

test('today is counted on the project clock', function () {
    config(['mpc.timezone' => 'Asia/Baghdad']);

    // 00:30 today in Baghdad, which is still yesterday in UTC.
    AuditLog::factory()->create([
        'created_at' => now()->setTimezone('Asia/Baghdad')->startOfDay()->addMinutes(30)->utc(),
    ]);
    // 23:00 yesterday in Baghdad.
    AuditLog::factory()->create([
        'created_at' => now()->setTimezone('Asia/Baghdad')->startOfDay()->subHour()->utc(),
    ]);

    expect(auditProps()['summary']['today'])->toBe(1);
});

test('the newest event is first, and ties do not reshuffle between pages', function () {
    // Every row shares a timestamp, which is what a request and the model
    // events it triggers actually look like.
    AuditLog::factory()->count(30)->create(['created_at' => '2026-09-01 12:00:00']);

    $first = collect(auditProps()['logs']['data'])->pluck('id');
    $second = collect(auditProps(['page' => 2])['logs']['data'])->pluck('id');

    expect($first)->toHaveCount(25)
        ->and($second)->toHaveCount(5)
        ->and($first->intersect($second))->toBeEmpty();
});

test('it renders with nothing recorded at all', function () {
    $props = auditProps();

    expect($props['logs']['data'])->toBe([])
        ->and($props['summary'])->toBe(['total' => 0, 'actors' => 0, 'changes' => 0, 'today' => 0])
        ->and($props['entityOptions'])->toBe([])
        ->and($props['actionOptions'])->toBe([])
        ->and($props['actorOptions'])->toBe([]);
});

/**
 * Audit logs are append-only. The model throws on update and delete, so a
 * mutation route here could only ever be a way to ask it to.
 */
test('the audit trail exposes no way to change itself', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'audit-log'));

    expect($routes)->toHaveCount(1)
        ->and($routes->first()->methods())->toBe(['GET', 'HEAD']);
});

test('a signed-out visitor is turned away', function () {
    $this->get(route('admin.audit-logs.index'))->assertRedirect('/login');
});

test('a user without an admin role is turned away', function () {
    $role = Role::firstOrCreate(['slug' => 'vendor_manager'], ['name' => 'Vendor Manager', 'is_system' => false]);

    $this->actingAs(User::factory()->create(['role_id' => $role->id]))
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();
});
