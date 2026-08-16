<?php

use App\Console\Commands\ResetDataCommand;
use App\Models\ActivityLog;
use App\Models\Addendum;
use App\Models\ApprovalDecision;
use App\Models\AuditLog;
use App\Models\Award;
use App\Models\BidBoqPrice;
use App\Models\BidDocument;
use App\Models\Category;
use App\Models\Clarification;
use App\Models\CommitteeMember;
use App\Models\DocumentAccessLog;
use App\Models\EvaluationScore;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Models\TenderDocument;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Database\Seeders\CategorySeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Tables mpc:reset-data must always empty. Spelled out here rather than read
 * from the command so that adding a table to one and not the other fails.
 */
function purgedTables(): array
{
    return [
        'bid_boq_prices', 'bid_documents', 'evaluation_scores', 'evaluation_reports',
        'awards', 'bids', 'committee_members', 'evaluation_committees',
        'evaluation_criteria', 'approval_decisions', 'approval_requests',
        'clarifications', 'addenda', 'tender_documents', 'boq_items', 'boq_sections',
        'tender_categories', 'tenders', 'notifications', 'notification_logs',
        'activity_logs', 'document_access_logs', 'audit_logs',
        'vendor_category_request_evidence', 'vendor_category_request_items',
        'vendor_category_requests', 'vendor_documents', 'vendor_categories',
        'vendor_password_reset_tokens', 'vendors',
    ];
}

/**
 * Populate every table the command targets. Factories self-chain, so a handful
 * of leaf creates pulls in tenders, bids, vendors, projects and users; the
 * remainder are inserted directly because they have no factory.
 */
function buildTransactionalGraph(): array
{
    $award = Award::factory()->create();
    $tender = $award->tender;
    $vendor = $award->vendor;

    BidBoqPrice::factory()->create(['bid_id' => $award->bid_id]);
    BidDocument::factory()->create(['bid_id' => $award->bid_id]);
    EvaluationScore::factory()->create(['bid_id' => $award->bid_id]);
    CommitteeMember::factory()->create();
    ApprovalDecision::factory()->create();
    Addendum::factory()->create(['tender_id' => $tender->id]);
    Clarification::factory()->create(['tender_id' => $tender->id]);
    TenderDocument::factory()->create(['tender_id' => $tender->id]);
    VendorDocument::factory()->create(['vendor_id' => $vendor->id]);
    NotificationLog::factory()->create();
    ActivityLog::factory()->create();
    AuditLog::factory()->create(['vendor_id' => $vendor->id]);
    DocumentAccessLog::factory()->create(['vendor_id' => $vendor->id]);

    $category = Category::factory()->create();

    DB::table('vendor_categories')->insert([
        'id' => (string) Str::uuid(),
        'vendor_id' => $vendor->id,
        'category_id' => $category->id,
        'created_at' => now(),
    ]);

    DB::table('tender_categories')->insert([
        'id' => (string) Str::uuid(),
        'tender_id' => $tender->id,
        'category_id' => $category->id,
    ]);

    DB::table('vendor_password_reset_tokens')->insert([
        'email' => $vendor->email,
        'token' => Str::random(40),
        'created_at' => now(),
    ]);

    $requestId = (string) Str::uuid();
    DB::table('vendor_category_requests')->insert([
        'id' => $requestId,
        'vendor_id' => $vendor->id,
        'justification' => 'Expanding into MEP works.',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('vendor_category_request_items')->insert([
        'id' => (string) Str::uuid(),
        'request_id' => $requestId,
        'category_id' => $category->id,
        'operation' => 'add',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('vendor_category_request_evidence')->insert([
        'id' => (string) Str::uuid(),
        'request_id' => $requestId,
        'path' => 'vendor-evidence/licence.pdf',
        'original_name' => 'licence.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
        'uploaded_by_vendor_id' => $vendor->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['tender' => $tender, 'vendor' => $vendor, 'award' => $award];
}

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
        CategorySeeder::class,
        SystemSettingSeeder::class,
        NotificationTemplateSeeder::class,
    ]);
});

test('it empties every transactional table', function () {
    buildTransactionalGraph();

    // Sanity: the fixture really did populate everything, otherwise the
    // assertions below would pass against a graph that was never built.
    foreach (purgedTables() as $table) {
        expect(DB::table($table)->count())->toBeGreaterThan(0, "fixture did not populate {$table}");
    }

    $this->artisan('mpc:reset-data --force')->assertSuccessful();

    foreach (purgedTables() as $table) {
        expect(DB::table($table)->count())->toBe(0, "{$table} still holds rows");
    }
});

test('it preserves reference data and users', function () {
    buildTransactionalGraph();

    $roles = DB::table('roles')->count();
    $permissions = DB::table('permissions')->count();
    $rolePermissions = DB::table('role_permissions')->count();
    $categories = DB::table('categories')->count();
    $settings = DB::table('system_settings')->count();
    $templates = DB::table('notification_templates')->count();
    $users = DB::table('users')->count();

    expect($users)->toBeGreaterThan(0);

    $this->artisan('mpc:reset-data --force')->assertSuccessful();

    expect(DB::table('roles')->count())->toBe($roles);
    expect(DB::table('permissions')->count())->toBe($permissions);
    expect(DB::table('role_permissions')->count())->toBe($rolePermissions);
    expect(DB::table('categories')->count())->toBe($categories);
    expect(DB::table('system_settings')->count())->toBe($settings);
    expect(DB::table('notification_templates')->count())->toBe($templates);
    expect(DB::table('users')->count())->toBe($users);
});

test('it keeps projects unless --with-projects is passed', function () {
    buildTransactionalGraph();
    $user = User::first();
    $project = Project::first();
    DB::table('user_project')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'project_id' => $project->id,
        'project_role' => 'member',
        'assigned_at' => now(),
        'assigned_by' => $user->id,
    ]);

    $this->artisan('mpc:reset-data --force')->assertSuccessful();

    expect(Project::count())->toBeGreaterThan(0);
    expect(DB::table('user_project')->count())->toBeGreaterThan(0);
});

test('--with-projects removes projects and their user assignments', function () {
    buildTransactionalGraph();
    $user = User::first();
    $project = Project::first();
    DB::table('user_project')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'project_id' => $project->id,
        'project_role' => 'member',
        'assigned_at' => now(),
        'assigned_by' => $user->id,
    ]);

    $this->artisan('mpc:reset-data --with-projects --force')->assertSuccessful();

    expect(Project::count())->toBe(0);
    expect(DB::table('user_project')->count())->toBe(0);
    // Users are not projects — they must survive either way.
    expect(User::count())->toBeGreaterThan(0);
});

test('--with-projects warns that tenders stay invisible until users are reassigned', function () {
    buildTransactionalGraph();

    $this->artisan('mpc:reset-data --with-projects --force')
        ->expectsOutputToContain('assigned to their project')
        ->assertSuccessful();
});

test('the default run does not emit the project reassignment warning', function () {
    buildTransactionalGraph();

    $this->artisan('mpc:reset-data --force')
        ->doesntExpectOutputToContain('assigned to their project')
        ->assertSuccessful();
});

test('--keep-audit retains the audit trail while still removing vendors', function () {
    buildTransactionalGraph();

    $auditBefore = DB::table('audit_logs')->count();
    expect($auditBefore)->toBeGreaterThan(0);

    $this->artisan('mpc:reset-data --keep-audit --force')->assertSuccessful();

    expect(DB::table('audit_logs')->count())->toBe($auditBefore);
    expect(DB::table('document_access_logs')->count())->toBeGreaterThan(0);
    expect(Vendor::count())->toBe(0);

    // vendor_id is nullOnDelete, so the retained rows survive with a null FK
    // rather than blocking the vendor delete.
    expect(DB::table('audit_logs')->whereNotNull('vendor_id')->count())->toBe(0);
});

test('--dry-run reports without changing anything', function () {
    buildTransactionalGraph();

    $before = collect(purgedTables())->mapWithKeys(
        fn ($t) => [$t => DB::table($t)->count()]
    );

    $this->artisan('mpc:reset-data --dry-run')->assertSuccessful();

    foreach ($before as $table => $count) {
        expect(DB::table($table)->count())->toBe($count, "{$table} changed during a dry run");
    }
});

test('it succeeds and reports nothing to do on an already-clean system', function () {
    $this->artisan('mpc:reset-data --force')
        ->expectsOutputToContain('Nothing to remove')
        ->assertSuccessful();
});

test('every table in the schema is classified as purge, keep, project or framework', function () {
    $tables = [];
    foreach (glob(database_path('migrations/*.php')) as $file) {
        if (preg_match_all('/Schema::create\(.([a-z_]+)./', file_get_contents($file), $m)) {
            foreach ($m[1] as $table) {
                $tables[$table] = true;
            }
        }
    }

    $command = new ReflectionClass(ResetDataCommand::class);

    // Laravel/Pulse scratch tables — nothing the reset command should touch.
    $framework = [
        'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'pulse_aggregates', 'pulse_entries', 'pulse_values',
        'sessions', 'password_reset_tokens',
    ];

    $accounted = array_merge(
        $command->getConstant('PURGE_ORDER'),
        $command->getConstant('PROJECT_TABLES'),
        $command->getConstant('KEEP_TABLES'),
        $framework,
    );

    $unaccounted = array_values(array_diff(array_keys($tables), $accounted));
    $nonexistent = array_values(array_diff($accounted, array_keys($tables)));

    expect($unaccounted)->toBe([], 'new table(s) must be added to ResetDataCommand: '.implode(', ', $unaccounted));
    expect($nonexistent)->toBe([], 'ResetDataCommand references table(s) that no longer exist: '.implode(', ', $nonexistent));

    $purge = $command->getConstant('PURGE_ORDER');
    expect($purge)->toBe(array_values(array_unique($purge)), 'PURGE_ORDER contains duplicates');

    // Every table holding an upload path must actually be purged, or --with-files
    // would collect paths for rows that are never deleted.
    $fileTables = array_keys($command->getConstant('FILE_COLUMNS'));
    expect(array_diff($fileTables, $purge))->toBe([]);
});

test('--reseed restores reference data without touching the admin password', function () {
    buildTransactionalGraph();

    $admin = User::factory()->create(['email' => 'admin@mpc-group.com']);
    $passwordBefore = $admin->fresh()->password;

    // Wipe a reference table so we can prove --reseed puts it back.
    DB::table('notification_templates')->delete();

    $this->artisan('mpc:reset-data --reseed --force')
        // Re-seeding overwrites tuned reference data, so it must say so.
        ->expectsOutputToContain('overwrites reference data')
        ->assertSuccessful();

    expect(DB::table('notification_templates')->count())->toBeGreaterThan(0);
    expect($admin->fresh()->password)->toBe($passwordBefore);
});

test('a declined --reseed prompt still leaves the purge applied', function () {
    buildTransactionalGraph();
    DB::table('notification_templates')->delete();

    // Derived, not hardcoded — the fixture's row count is not fixed.
    $total = collect(purgedTables())->sum(fn ($t) => DB::table($t)->count());

    $this->artisan('mpc:reset-data --reseed')
        ->expectsConfirmation("Permanently delete these {$total} rows? This cannot be undone.", 'yes')
        ->expectsConfirmation('Re-seed reference data anyway?', 'no')
        ->assertSuccessful();

    // Purge still happened; only the overwrite was declined.
    expect(Vendor::count())->toBe(0);
    expect(DB::table('notification_templates')->count())->toBe(0);
});
