<?php

namespace App\Console\Commands;

use Database\Seeders\CategorySeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Clears transactional data so the system can go live with real vendors,
 * while preserving the reference data it needs to function.
 *
 * KEPT: roles, permissions, role_permissions, categories, system_settings,
 * notification_templates, users (and, unless --with-projects, projects).
 *
 * PURGED: every vendor, tender, bid, evaluation, approval, award,
 * notification and log record.
 *
 * Intended to be run once before real data entry begins:
 *
 *     php artisan mpc:reset-data --dry-run     # see what would go
 *     php artisan mpc:reset-data --with-files  # then do it for real
 */
class ResetDataCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'mpc:reset-data
        {--dry-run : Report what would be removed without changing anything}
        {--with-projects : Also delete projects and their user assignments}
        {--with-files : Also delete the uploaded files behind the purged records}
        {--keep-audit : Preserve audit_logs and document_access_logs}
        {--reseed : Re-run the reference seeders, RESETTING settings/categories/role grants to defaults}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Delete demo/transactional data (vendors, tenders, bids, evaluations, logs) while keeping roles, permissions, categories, settings, notification templates and users.';

    /**
     * Transactional tables in a safe DELETE order — children before parents —
     * so every statement runs with foreign key checks left ON. This ordering is
     * load-bearing: reordering it is a correctness change, not a cosmetic one.
     *
     * Note `awards` sits before both `bids` and `tenders` because it references
     * both, and `bid_boq_prices` sits before `boq_items` for the same reason.
     */
    private const PURGE_ORDER = [
        // Bid pricing and attachments — reference bids and BOQ items.
        'bid_boq_prices',
        'bid_documents',
        // Evaluation artefacts — reference bids, criteria and committees.
        'evaluation_scores',
        'evaluation_reports',
        // Awards reference bids AND tenders, so they must precede both.
        'awards',
        'bids',
        'committee_members',
        'evaluation_committees',
        'evaluation_criteria',
        // Approval workflow hangs off tenders.
        'approval_decisions',
        'approval_requests',
        // Pending dual-authorisation openings, likewise.
        'bid_opening_requests',
        // Tender children, then tenders.
        'clarifications',
        'addenda',
        'tender_documents',
        'boq_items',
        'boq_sections',
        'tender_categories',
        'tenders',
        // Delivery + activity records.
        'notifications',
        'notification_logs',
        'activity_logs',
        // Access/audit trails — dropped here so they precede vendors. Both FK
        // into vendors with nullOnDelete, so --keep-audit is still safe: the
        // vendor_id is nulled rather than the delete failing.
        'document_access_logs',
        'audit_logs',
        // Vendor children, then vendors.
        'vendor_category_request_evidence',
        'vendor_category_request_items',
        'vendor_category_requests',
        'vendor_documents',
        'vendor_categories',
        'vendor_password_reset_tokens',
        'vendors',
    ];

    /** Excluded from the purge when --keep-audit is passed. */
    private const AUDIT_TABLES = ['document_access_logs', 'audit_logs'];

    /**
     * Only purged when --with-projects is passed; children first.
     *
     * `user_project.project_id` is cascadeOnDelete, so dropping projects would
     * clear the pivot anyway — it is listed explicitly so the behaviour does not
     * silently depend on a constraint a future migration might relax. The two
     * are deliberately bound to one flag: emptying the pivot while keeping
     * projects would hide every tender from every user (see warnOnProjectPurge).
     */
    private const PROJECT_TABLES = ['user_project', 'projects'];

    /** Reference data this command exists to protect. Verified after the purge. */
    private const KEEP_TABLES = [
        'roles',
        'permissions',
        'role_permissions',
        'categories',
        'system_settings',
        'notification_templates',
        'users',
    ];

    /** Tables whose rows point at an uploaded file: table => path column. */
    private const FILE_COLUMNS = [
        'vendor_documents' => 'file_path',
        'tender_documents' => 'file_path',
        'addenda' => 'file_path',
        'bid_documents' => 'file_path',
        'evaluation_reports' => 'file_path',
        'awards' => 'letter_file_path',
        'vendor_category_request_evidence' => 'path',
    ];

    /**
     * Reference seeders re-run by --reseed. AdminUserSeeder is excluded: it
     * seeds an account rather than reference data, and `users` is a KEEP_TABLE,
     * so the admin it would create is never purged in the first place.
     */
    private const RESEED_CLASSES = [
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
        CategorySeeder::class,
        SystemSettingSeeder::class,
        NotificationTemplateSeeder::class,
    ];

    public function handle(): int
    {
        $tables = $this->tablesToPurge();
        $counts = $this->countRows($tables);
        $total = array_sum($counts);

        $this->newLine();
        $this->components->info('MPC e-Tender — data reset');
        $this->line('  Connection: <comment>'.config('database.default').'</comment>  Database: <comment>'.config('database.connections.'.config('database.default').'.database').'</comment>  Env: <comment>'.app()->environment().'</comment>');

        $this->renderPurgeTable($counts, $total);
        $this->renderKeepTable();

        if ($total === 0) {
            $this->components->info('Nothing to remove — the system already holds no transactional data.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->components->warn('Dry run — nothing was changed. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        // Laravel Cloud's command runner, CI and cron all run without a TTY, so
        // neither prompt below can be answered there: Symfony returns the
        // default and the run reports a bare "Command cancelled". Say what to
        // do instead of leaving the operator to guess which flag is missing.
        if (! $this->option('force') && ! $this->input->isInteractive()) {
            $this->newLine();
            $this->components->error(
                'This deletes data and there is no interactive terminal to confirm from. '
                .'Re-run with --force.'
            );

            return self::FAILURE;
        }

        // Blocks in production unless --force. Layered under our own prompt so
        // a local run is still deliberate.
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Permanently delete these {$total} rows? This cannot be undone.", false)) {
            $this->components->warn('Aborted — nothing was changed.');

            return self::FAILURE;
        }

        // Paths must be read before the rows that hold them are deleted.
        $paths = $this->option('with-files') ? $this->collectFilePaths($tables) : [];

        DB::transaction(function () use ($tables) {
            foreach ($tables as $table) {
                DB::table($table)->delete();
            }
        });

        $this->components->info('Database rows removed.');

        if ($this->option('with-files')) {
            $this->purgeFiles($paths);
        }

        if ($this->option('reseed')) {
            $this->reseed();
        }

        return $this->verify($tables);
    }

    /** Purge list, adjusted for --keep-audit and --with-projects. */
    private function tablesToPurge(): array
    {
        $tables = self::PURGE_ORDER;

        if ($this->option('keep-audit')) {
            $tables = array_values(array_diff($tables, self::AUDIT_TABLES));
        }

        if ($this->option('with-projects')) {
            $tables = array_merge($tables, self::PROJECT_TABLES);
        }

        // Skip anything not migrated yet rather than fataling mid-run.
        return array_values(array_filter($tables, fn ($t) => Schema::hasTable($t)));
    }

    /** @return array<string,int> */
    private function countRows(array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    private function renderPurgeTable(array $counts, int $total): void
    {
        $rows = [];
        foreach ($counts as $table => $count) {
            // Keep the output readable — empty tables are noise.
            if ($count > 0) {
                $rows[] = [$table, number_format($count)];
            }
        }

        $this->newLine();
        $this->line('  <fg=red;options=bold>WILL BE DELETED</>');

        if ($rows === []) {
            $this->line('  <fg=gray>(all target tables are already empty)</>');

            return;
        }

        $rows[] = ['<options=bold>TOTAL</>', '<options=bold>'.number_format($total).'</>'];
        $this->table(['Table', 'Rows'], $rows);
    }

    private function renderKeepTable(): void
    {
        $rows = [];
        foreach (self::KEEP_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $rows[] = [$table, number_format(DB::table($table)->count())];
            }
        }

        if (! $this->option('with-projects') && Schema::hasTable('projects')) {
            $rows[] = ['projects', number_format(DB::table('projects')->count())];
            $rows[] = ['user_project', number_format(DB::table('user_project')->count())];
        }

        $this->line('  <fg=green;options=bold>WILL BE KEPT</>');
        $this->table(['Table', 'Rows'], $rows);
    }

    /**
     * Read every stored file path before its row is deleted.
     *
     * @return array<int,string>
     */
    private function collectFilePaths(array $tables): array
    {
        $paths = [];

        foreach (self::FILE_COLUMNS as $table => $column) {
            if (! in_array($table, $tables, true) || ! Schema::hasTable($table)) {
                continue;
            }

            $paths = array_merge(
                $paths,
                DB::table($table)->whereNotNull($column)->pluck($column)->all()
            );
        }

        return array_values(array_unique(array_filter($paths)));
    }

    private function purgeFiles(array $paths): void
    {
        if ($paths === []) {
            $this->components->info('No stored files were referenced by the removed rows.');

            return;
        }

        $disk = Storage::disk('s3');
        $deleted = 0;
        $failed = [];

        $bar = $this->output->createProgressBar(count($paths));
        $bar->start();

        foreach ($paths as $path) {
            try {
                // delete() returns true for an already-absent object, which is
                // the outcome we want anyway.
                $disk->delete($path);
                $deleted++;
            } catch (\Throwable $e) {
                $failed[] = $path.' — '.$e->getMessage();
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->components->info("Removed {$deleted} stored file(s).");

        if ($failed !== []) {
            $this->components->warn(count($failed).' file(s) could not be deleted; the database rows are already gone:');
            foreach (array_slice($failed, 0, 10) as $line) {
                $this->line('    '.$line);
            }
        }
    }

    /**
     * Re-run the reference seeders.
     *
     * This OVERWRITES rather than fills gaps, and the caller needs to know:
     * SystemSettingSeeder does updateOrCreate(['key'], array_merge($setting))
     * so a tuned value reverts to its default, CategorySeeder likewise reverts
     * edited names, and RolePermissionSeeder uses sync() which drops any custom
     * permission grant made under /admin/roles. Only worth running when the
     * reference tables are actually empty.
     */
    private function reseed(): bool
    {
        $this->newLine();
        $this->components->warn(
            'Re-seeding overwrites reference data with its defaults: tuned system settings revert, '.
            'edited category names revert, and custom role permission grants are replaced by sync().'
        );

        if (! $this->option('force') && ! $this->confirm('Re-seed reference data anyway?', false)) {
            $this->components->warn('Re-seed skipped. Everything else was applied.');

            return false;
        }

        $this->components->info('Re-running reference seeders…');

        foreach (self::RESEED_CLASSES as $class) {
            $this->callSilent('db:seed', ['--class' => $class, '--force' => true]);
            $this->line('    <fg=green>✓</> '.class_basename($class));
        }

        $this->line('    <fg=gray>AdminUserSeeder skipped — users are never purged, so there is nothing to restore.</>');

        return true;
    }

    /**
     * Tender visibility is scoped through the user_project pivot
     * (Tender\TenderController::index: whereIn('project_id', $user->projects())),
     * so with no project assignments every tender is invisible to every user —
     * super_admin included — with no error shown. Say so plainly rather than
     * letting it look like the tenders failed to save.
     */
    private function warnOnProjectPurge(): void
    {
        if (! $this->option('with-projects')) {
            return;
        }

        $this->components->warn(
            'Projects and user assignments were removed. Tenders are only visible to users '.
            'assigned to their project, so create a project and assign users under '.
            '/admin/projects before creating tenders — otherwise the tender list will look '.
            'empty even to a super admin.'
        );
    }

    /** Confirm the purge emptied what it should and left reference data intact. */
    private function verify(array $tables): int
    {
        $leftover = [];
        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            if ($count > 0) {
                $leftover[$table] = $count;
            }
        }

        $missingReference = [];
        foreach (self::KEEP_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->count() === 0) {
                $missingReference[] = $table;
            }
        }

        $this->newLine();

        if ($leftover !== []) {
            $this->components->error('Some target tables still hold rows:');
            foreach ($leftover as $table => $count) {
                $this->line("    {$table}: {$count}");
            }

            return self::FAILURE;
        }

        if ($missingReference !== []) {
            $this->components->warn(
                'Reference data is empty in: '.implode(', ', $missingReference).
                '. Run `php artisan db:seed` (or re-run with --reseed) before using the system.'
            );
        }

        $this->warnOnProjectPurge();

        $this->components->info('System is clean and ready for real data.');

        return self::SUCCESS;
    }
}
