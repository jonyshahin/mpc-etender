<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\DocumentAccessLog;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use App\Services\Export\MultipartUpload;
use App\Services\Export\StorageUpload;
use Aws\S3\Exception\S3Exception;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * Packs every vendor and their prequalification documents into zip files for
 * an MPC user to download:
 *
 *     vendors.csv                  one row per vendor
 *     documents.csv                one row per document: where it landed, or why not
 *     <Company name>/<Type> - <Title>.pdf
 *
 * The zips go to the private bucket and are handed out as short-lived signed
 * links, so the files leave storage by no other route. Every exported document
 * is written to document_access_logs and every vendor gets an audit row, both
 * against the user named in --by.
 *
 * The command runs on a live web instance whose local disk is a small
 * allowance (512 MB per GB of RAM on Laravel Cloud); going over it gets the
 * instance stopped, with no error from the command. So nothing is staged
 * there: each zip streams straight into the bucket as a multipart upload
 * (StorageUpload), and the only local copy is the document being read. An
 * export larger than --part-size is split into parts, for easier downloads;
 * extracting them all into one folder gives the full tree.
 *
 * Each run replaces the previous export; --clean removes it once downloaded.
 *
 *     php artisan mpc:export-vendors --by=you@mpc-group.com --dry-run
 *     php artisan mpc:export-vendors --by=you@mpc-group.com
 *     php artisan mpc:export-vendors --clean
 */
class ExportVendorsCommand extends Command
{
    /** Where exports live on the s3 disk. Nothing else is written here. */
    public const DIRECTORY = 'exports/vendors';

    /**
     * Roles that reach the vendor screens: the admin route group in
     * routes/web.php is behind role:admin,super_admin, whatever permissions a
     * role has been granted.
     */
    private const ROLES = ['admin', Role::SUPER_ADMIN];

    /** Root entries a vendor folder must never take. */
    private const RESERVED = ['vendors.csv', 'documents.csv'];

    /** How the export identifies itself in the access and audit logs. */
    private const AGENT = 'artisan mpc:export-vendors';

    /**
     * The command peaks around 100 MB (ZipStream reads in 16 MB chunks, the
     * upload holds an 8 MB part); PHP's usual 128 MB default leaves too little
     * room on top of the framework.
     */
    private const MEMORY_LIMIT = '256M';

    /**
     * How long the export lock lives. A run killed by Laravel Cloud's 30-minute
     * command limit never reaches its finally block, so the lock is released by
     * expiring: just after the runner could still be going, not an hour later.
     */
    public const LOCK_SECONDS = 35 * 60;

    protected $signature = 'mpc:export-vendors
        {--by= : Email of the MPC user taking the export; recorded against every file}
        {--part-size=1024 : Largest size of each zip, in MB (1-10240); bigger exports are split into parts}
        {--expires=30 : Minutes the download links stay valid (1-1440)}
        {--dry-run : Report what would be exported without writing anything}
        {--clean : Delete earlier exports from storage and exit}';

    protected $description = 'Export every vendor and their prequalification documents as zips with one folder per vendor, behind short-lived download links.';

    private string $stamp;

    private int $part = 0;

    /** Whether the whole export fits one zip, decided up front so it can be named for it. */
    private bool $single = false;

    private ?StorageUpload $upload = null;

    private ?ZipStream $zip = null;

    private bool $partHasDocuments = false;

    /** @var array<int, string> storage paths of the parts uploaded by this run */
    private array $uploaded = [];

    public function handle(): int
    {
        $this->raiseMemoryLimit();

        if ($this->option('clean')) {
            return $this->locked(function () {
                [$removed, $leftFiles] = $this->deleteExports();
                [$aborted, $leftUploads] = $this->abortStaleUploads();
                $left = [...$leftFiles, ...$leftUploads];

                $this->info("Removed {$removed} export file(s) from storage".($aborted > 0 ? " and abandoned {$aborted} unfinished upload(s)" : '').'.');

                if ($left !== []) {
                    $this->error('Storage refused to delete these, so they are still there. Run --clean again; if it keeps failing, delete them in the Laravel Cloud bucket browser:');
                    foreach ($left as $path) {
                        $this->line("  - {$path}");
                    }

                    return self::FAILURE;
                }

                return self::SUCCESS;
            });
        }

        $user = $this->resolveUser();
        $expires = $this->integerOption('expires', 1, 1440);
        $partMb = $this->integerOption('part-size', 1, 10240);

        if ($user === null || $expires === null || $partMb === null) {
            return self::FAILURE;
        }

        $vendors = Vendor::query()
            ->with([
                'categories',
                'documents' => fn ($query) => $query->orderBy('document_type')->orderBy('created_at'),
            ])
            ->orderBy('company_name')
            ->orderBy('id')
            ->get();

        $budget = $partMb * 1024 * 1024;
        $documents = $vendors->flatMap(fn (Vendor $vendor) => $vendor->documents);
        $recorded = (int) $documents->sum('file_size');

        // Small enough for one zip: then it is written as one, whatever the
        // per-file overhead adds, and named without a part number.
        $single = $recorded <= $budget;

        $this->table(
            ['Vendors', 'Documents', 'Recorded size', 'Largest document', 'Vendors with no documents', 'Zip files (about)'],
            [[
                $vendors->count(),
                $documents->count(),
                $this->formatBytes($recorded),
                $this->formatBytes((int) $documents->max('file_size')),
                $vendors->filter(fn (Vendor $vendor) => $vendor->documents->isEmpty())->count(),
                $single ? 1 : (int) ceil($recorded / $budget),
            ]],
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        return $this->locked(fn () => $this->export($user, $vendors, $budget, $single, $expires));
    }

    /**
     * Give the run the memory it needs. Running out kills PHP outright: no
     * catch or finally runs, and nothing reaches the Commands panel — Laravel's
     * own fatal-error handler runs out of memory again while rendering, and
     * takes every later shutdown function down with it. Hence the headroom,
     * and the progress lines, which at least show how far a run got.
     */
    private function raiseMemoryLimit(): void
    {
        $limit = ini_get('memory_limit');

        if ($limit !== false && $limit !== '-1' && ini_parse_quantity($limit) < ini_parse_quantity(self::MEMORY_LIMIT)) {
            ini_set('memory_limit', self::MEMORY_LIMIT);
        }
    }

    /**
     * Run under a lock so two exports (or an export and --clean) never work on
     * the same files at once.
     */
    private function locked(callable $callback): int
    {
        $lock = Cache::lock('mpc:export-vendors', self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->error('Another vendor export is running. Try again when it has finished.');

            return self::FAILURE;
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /** @param  Collection<int, Vendor>  $vendors */
    private function export(User $user, Collection $vendors, int $budget, bool $single, int $expires): int
    {
        // Artisan keeps one instance of a command, so a second run in the same
        // process must not inherit the first one's parts.
        $this->part = 0;
        $this->upload = null;
        $this->zip = null;
        $this->uploaded = [];
        $this->single = $single;

        // The time, for people; the random part, so that no two runs — even
        // in the same second — can share a name, and a failed run's clean-up
        // can only ever touch its own files.
        $this->stamp = now()->timezone(config('mpc.timezone'))->format('Ymd-His').'-'.Str::lower(Str::random(4));
        $folders = $this->folderNames($vendors);

        // A run Cloud stopped mid-upload leaves an unfinished multipart upload
        // behind: invisible, but still stored. Safe to clear: we hold the lock.
        // Not this run's problem if it cannot be — but worth saying.
        [, $stuck] = $this->abortStaleUploads();

        if ($stuck !== []) {
            $this->warn('An earlier run left unfinished uploads that could not be cancelled now; run `php artisan mpc:export-vendors --clean` later:');
            foreach ($stuck as $what) {
                $this->line("  - {$what}");
            }
        }

        try {
            [$outcomes, $parts] = $this->writeParts($vendors, $folders, $budget);

            // Only now can anything be retrieved, so only now is it logged —
            // all of it or none of it.
            DB::transaction(fn () => $this->recordAccess($user, $vendors, $outcomes));
        } catch (Throwable $e) {
            // Handled here rather than left to Laravel's handler: what was
            // uploaded must go before anything else happens, and what is said
            // must match what is actually left behind.
            $inFlight = $this->upload;
            $this->upload = null;
            $left = [...$this->discardInFlight($inFlight), ...$this->discardUploads()];

            // A broken log channel must not cost the operator the message.
            try {
                report($e);
            } catch (Throwable) {
            }

            $this->error('The export failed: '.$e->getMessage());

            if ($left === []) {
                $this->line('Nothing was logged as exported, and nothing it uploaded was kept.');
            } else {
                $this->error('Nothing was logged as exported, but storage refused to remove these, which hold vendor documents. Run `php artisan mpc:export-vendors --clean`:');
                foreach ($left as $what) {
                    $this->line("  - {$what}");
                }
            }

            return self::FAILURE;
        }

        // Past the point of no return: the export is logged, so failing to
        // tidy away an older one must not take this one down with it.
        try {
            [, $left] = $this->deleteExports(except: $this->uploaded);
        } catch (Throwable) {
            $left = ['(the export folder could not be listed)'];
        }

        if ($left !== []) {
            $this->warn('An earlier export could not be removed from storage. Run `php artisan mpc:export-vendors --clean` once you have downloaded this one:');
            foreach ($left as $path) {
                $this->line("  - {$path}");
            }
        }

        return $this->report($vendors, $folders, $outcomes, $parts, $expires);
    }

    /**
     * Get rid of the zip that was being written when the run failed: cancel
     * its upload, and — if it got as far as finishing — delete its key too,
     * since storage may have finished it even though the request that did so
     * reported an error. The key is this run's own: names carry a random part.
     *
     * @return array<int, string> what may still be in storage
     */
    private function discardInFlight(?StorageUpload $upload): array
    {
        if ($upload === null) {
            return [];
        }

        $left = [];

        if (! $upload->abort()) {
            $left[] = "an unfinished upload of {$upload->path()}";
        }

        if (! $upload->mayExist()) {
            return $left;
        }

        try {
            $deleted = Storage::disk('s3')->delete($upload->path());
        } catch (Throwable) {
            $deleted = false;
        }

        if (! $deleted) {
            $left[] = $upload->path();
        }

        return $left;
    }

    /**
     * Remove this run's finished zips after a failure.
     *
     * @return array<int, string> the ones storage refused to delete
     */
    private function discardUploads(): array
    {
        $left = [];

        foreach ($this->uploaded as $path) {
            try {
                $deleted = Storage::disk('s3')->delete($path);
            } catch (Throwable) {
                $deleted = false;
            }

            if (! $deleted) {
                $left[] = $path;
            }
        }

        return $left;
    }

    /**
     * Abort unfinished multipart uploads under the export folder — what a run
     * stopped mid-upload leaves. Only call holding the lock, or it could abort
     * a live run's upload.
     *
     * @return array{0: int, 1: array<int, string>} how many were aborted, and what could not be
     */
    private function abortStaleUploads(): array
    {
        $disk = Storage::disk('s3');

        if (! $disk instanceof AwsS3V3Adapter) {
            return [0, []];
        }

        $bucket = $disk->getConfig()['bucket'];
        $aborted = 0;
        $left = [];

        try {
            $uploads = $disk->getClient()->listMultipartUploads([
                'Bucket' => $bucket,
                'Prefix' => $disk->path(self::DIRECTORY.'/'),
            ])['Uploads'] ?? [];
        } catch (Throwable) {
            return [0, ['(unfinished uploads could not be listed)']];
        }

        // Each on its own: one refusal must not leave the rest untried.
        foreach ($uploads as $upload) {
            try {
                $disk->getClient()->abortMultipartUpload([
                    'Bucket' => $bucket,
                    'Key' => $upload['Key'],
                    'UploadId' => $upload['UploadId'],
                ]);
                $aborted++;
            } catch (S3Exception $e) {
                MultipartUpload::isNoSuchUpload($e) ? $aborted++ : $left[] = "an unfinished upload of {$upload['Key']}";
            } catch (Throwable) {
                $left[] = "an unfinished upload of {$upload['Key']}";
            }
        }

        return [$aborted, $left];
    }

    /**
     * Stream every document into size-bounded zips, uploading each as it is
     * finished. Returns each document's outcome and the parts' storage paths.
     *
     * A vendor is kept in one part unless it alone is over the budget. The two
     * CSVs go in the last part, once every document's outcome is known.
     *
     * @param  Collection<int, Vendor>  $vendors
     * @param  array<string, string>  $folders
     * @return array{0: array<string, array{document: VendorDocument, status: string, file: string, part: int}>, 1: array<int, string>}
     */
    private function writeParts(Collection $vendors, array $folders, int $budget): array
    {
        $outcomes = [];
        $this->openPart();

        foreach ($vendors->values() as $i => $vendor) {
            $folder = $folders[$vendor->id];
            $vendorBytes = (int) $vendor->documents->sum('file_size');

            if (! $this->single && $this->partHasDocuments && $vendorBytes <= $budget && $this->partBytes() + $vendorBytes > $budget) {
                $this->nextPart();
            }

            // Progress, so a run Cloud stops shows in the panel how far it got.
            $this->line(sprintf('[%d/%d] %s — %d document(s)', $i + 1, $vendors->count(), $folder, $vendor->documents->count()));

            $this->zip->addDirectory($folder, lastModificationDateTime: $this->zoned($vendor->created_at));
            $used = [];

            foreach ($vendor->documents as $document) {
                if (! $this->single && $this->partHasDocuments && $this->partBytes() + (int) $document->file_size > $budget) {
                    $this->nextPart();
                    $this->zip->addDirectory($folder, lastModificationDateTime: $this->zoned($vendor->created_at));
                }

                [$base, $extension] = $this->documentFileName($document);
                $fileName = $this->uniqueName($base, $used, $extension);
                $stream = $this->read((string) $document->file_path);

                if (is_resource($stream)) {
                    try {
                        $this->zip->addFileFromStream(
                            fileName: "{$folder}/{$fileName}",
                            stream: $stream,
                            lastModificationDateTime: $this->zoned($document->created_at),
                            // A sized header where the stream can be read twice;
                            // every unzip tool reads those.
                            enableZeroHeader: ! stream_get_meta_data($stream)['seekable'],
                        );
                    } finally {
                        fclose($stream);
                        // The S3 client buffers each document in a temp file
                        // held by reference cycles; collect them now so that
                        // file is gone before the next document, not at the end.
                        unset($stream);
                        gc_collect_cycles();
                    }

                    $this->partHasDocuments = true;
                    $outcomes[$document->id] = ['document' => $document, 'status' => 'included', 'file' => $fileName, 'part' => $this->part];
                } else {
                    // The run works from a list taken at the start; a vendor may
                    // have deleted a pending document since. That is not a
                    // file lost from storage.
                    $status = VendorDocument::whereKey($document->id)->exists() ? $stream : 'deleted during export';
                    $outcomes[$document->id] = ['document' => $document, 'status' => $status, 'file' => '', 'part' => 0];
                }
            }
        }

        $tz = config('mpc.timezone');
        $partName = fn (int $part) => $part === 0 ? '' : $this->partName($part);

        $this->zip->addFile(fileName: 'vendors.csv', lastModificationDateTime: $this->zoned(now()), data: $this->csv([
            'Folder', 'Company name', 'Company name (Arabic)', 'Trade licence no.', 'Contact person', 'Email',
            'Phone', 'WhatsApp', 'Address', 'City', 'Country', 'Website', 'Prequalification status',
            "Qualified at ({$tz})", 'Active', 'Categories', 'Documents', "Created at ({$tz})",
        ], $vendors->map(fn (Vendor $vendor) => [
            $folders[$vendor->id],
            $vendor->company_name,
            $vendor->company_name_ar,
            $vendor->trade_license_no,
            $vendor->contact_person,
            $vendor->email,
            $vendor->phone,
            $vendor->whatsapp_number,
            $vendor->address,
            $vendor->city,
            $vendor->country,
            $vendor->website,
            $vendor->prequalification_status?->value,
            $this->localTime($vendor->qualified_at),
            $vendor->is_active ? 'yes' : 'no',
            $vendor->categories->pluck('name_en')->sort()->implode('; '),
            $vendor->documents->count(),
            $this->localTime($vendor->created_at),
        ])->all()));

        $this->zip->addFile(fileName: 'documents.csv', lastModificationDateTime: $this->zoned(now()), data: $this->csv([
            'Company name', 'Folder', 'Zip file', 'File', 'Document type', 'Title', 'Review status',
            'Issue date', 'Expiry date', "Uploaded at ({$tz})", 'Size (bytes)', 'In export',
        ], $vendors->flatMap(fn (Vendor $vendor) => $vendor->documents->map(function (VendorDocument $document) use ($vendor, $folders, $outcomes, $partName) {
            $outcome = $outcomes[$document->id];

            return [
                $vendor->company_name,
                $folders[$vendor->id],
                $partName($outcome['part']),
                $outcome['file'],
                $document->document_type?->value,
                $document->title,
                $document->status?->value,
                $document->issue_date?->toDateString(),
                $document->expiry_date?->toDateString(),
                $this->localTime($document->created_at),
                $document->file_size,
                $outcome['status'],
            ];
        }))->all()));

        $this->closePart();

        return [$outcomes, $this->uploaded];
    }

    /** Start the next zip, writing straight into storage as it goes. */
    private function openPart(): void
    {
        $this->part++;
        $this->upload = StorageUpload::to(
            Storage::disk('s3'),
            self::DIRECTORY.'/'.$this->partName($this->part),
            'application/zip',
        );

        $this->zip = new ZipStream(
            outputStream: $this->upload->stream(),
            sendHttpHeaders: false,
            // PDFs are already compressed; deflating them costs time and saves nothing.
            defaultCompressionMethod: CompressionMethod::STORE,
            defaultEnableZeroHeader: false,
        );
        $this->partHasDocuments = false;
    }

    private function nextPart(): void
    {
        $this->closePart();
        $this->openPart();
    }

    /** Write the zip's directory and finish its upload. */
    private function closePart(): void
    {
        $this->zip->finish();
        $this->uploaded[] = $path = $this->upload->complete();
        $this->line('Uploaded '.basename($path).' ('.$this->formatBytes($this->upload->bytes()).').');
        $this->upload = null;
    }

    private function partName(int $part): string
    {
        return $this->single
            ? "vendors-export-{$this->stamp}.zip"
            : "vendors-export-{$this->stamp}-part{$part}.zip";
    }

    private function partBytes(): int
    {
        return $this->upload->bytes();
    }

    /**
     * Open a document for reading, trying twice. When it cannot be read, says
     * why: "missing" when the object is not in the bucket at all, "read error"
     * for anything else (permissions, the network, the bucket timing out) —
     * which is worth a re-run, where "missing" is not.
     *
     * @return resource|string
     */
    private function read(string $path): mixed
    {
        if ($path === '') {
            return 'missing';
        }

        $disk = Storage::disk('s3');

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $stream = $disk->readStream($path);
            } catch (Throwable) {
                $stream = null;
            }

            if (is_resource($stream)) {
                return $stream;
            }
        }

        try {
            return $disk->exists($path) ? 'read error' : 'missing';
        } catch (Throwable) {
            return 'read error';
        }
    }

    /**
     * The MPC user the export is recorded against. They must be able to open
     * vendor documents in the app today — the admin download route's checks:
     * an admin or super admin role, a verified email, vendors.view — so the
     * export shows nothing they could not already open one file at a time.
     */
    private function resolveUser(): ?User
    {
        $email = trim((string) $this->option('by'));

        if ($email === '') {
            $this->error('Name the MPC user taking the export with --by=email. It is recorded against every exported file.');

            return null;
        }

        $user = User::where('email', $email)->first();

        if ($user === null || ! $user->is_active) {
            $this->error("No active MPC user with the email {$email}.");

            return null;
        }

        $allowed = in_array($user->role?->slug, self::ROLES, true)
            && $user->email_verified_at !== null
            && Gate::forUser($user)->allows('view', Vendor::class);

        if (! $allowed) {
            $this->error("{$email} cannot open vendor documents in the app, so cannot export them. That needs the admin or super admin role, a verified email and the vendors.view permission.");

            return null;
        }

        return $user;
    }

    private function integerOption(string $name, int $min, int $max): ?int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);

        if ($value === false) {
            $this->error("--{$name} must be a whole number between {$min} and {$max}.");

            return null;
        }

        return $value;
    }

    /**
     * @param  Collection<int, Vendor>  $vendors
     * @param  array<string, string>  $folders
     * @param  array<string, array{document: VendorDocument, status: string, file: string, part: int}>  $outcomes
     * @param  array<int, string>  $parts
     */
    private function report(Collection $vendors, array $folders, array $outcomes, array $parts, int $expires): int
    {
        $byStatus = collect($outcomes)->groupBy('status');

        $deleted = $byStatus->get('deleted during export', collect())->count();

        if ($deleted > 0) {
            $this->line("{$deleted} document(s) were deleted by their vendor while the export ran, and are not in it.");
        }

        foreach (['missing' => 'are recorded but missing from storage', 'read error' => 'could not be read from storage — run the export again'] as $status => $what) {
            $affected = $byStatus->get($status, collect());

            if ($affected->isNotEmpty()) {
                $this->warn("{$affected->count()} document(s) {$what}. They are listed as \"{$status}\" in documents.csv:");
                foreach ($affected as $outcome) {
                    $document = $outcome['document'];
                    $this->line("  - {$folders[$document->vendor_id]}: {$document->title} ({$document->file_path})");
                }
            }
        }

        $until = now()->addMinutes($expires);
        $this->newLine();
        $this->info("Exported {$vendors->count()} vendor(s) and {$byStatus->get('included', collect())->count()} document(s) in ".count($parts).' zip file(s).');
        $this->line("Download links, valid for {$expires} minute(s), until {$until->copy()->timezone(config('mpc.timezone'))->format('Y-m-d H:i')} (".config('mpc.timezone').'):');

        foreach ($parts as $path) {
            $this->line(Storage::disk('s3')->temporaryUrl($path, $until));
        }

        if (count($parts) > 1) {
            $this->comment('Extract every part into the same folder; vendors.csv and documents.csv are in the last one.');
        }

        $this->newLine();
        $this->comment('The zips stay in storage until the next export or `php artisan mpc:export-vendors --clean`. Run --clean once you have them.');

        return $byStatus->has('read error') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * One folder per vendor, named after the company. Characters Windows will
     * not accept in a path are replaced, and two vendors that would land in the
     * same folder (names differing only in case or punctuation), or a vendor
     * named like one of the CSVs, get a suffix.
     *
     * @param  Collection<int, Vendor>  $vendors
     * @return array<string, string> vendor id => folder name
     */
    private function folderNames(Collection $vendors): array
    {
        $used = array_fill_keys(self::RESERVED, true);
        $folders = [];

        foreach ($vendors as $vendor) {
            $folders[$vendor->id] = $this->uniqueName($this->safeName($vendor->company_name, 'Vendor'), $used);
        }

        return $folders;
    }

    /**
     * "<Type> - <Title>" and the stored file's extension, kept apart so a
     * duplicate gets its counter before the extension.
     *
     * @return array{0: string, 1: string}
     */
    private function documentFileName(VendorDocument $document): array
    {
        $type = $document->document_type ? Str::headline($document->document_type->value) : 'Document';
        $extension = strtolower(pathinfo((string) $document->file_path, PATHINFO_EXTENSION)) ?: 'pdf';

        // A title typed as "licence.pdf" would otherwise end up "….pdf.pdf".
        $title = preg_replace('/\.'.preg_quote($extension, '/').'$/i', '', (string) $document->title);

        return [$this->safeName("{$type} - {$title}", $type), $extension];
    }

    /** Strip what a Windows or macOS path cannot hold, and cap the length. */
    private function safeName(?string $name, string $fallback): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]+/u', '-', (string) $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name), ' .-');
        $name = trim(Str::limit($name, 80, ''), ' .-');

        return $name !== '' ? $name : $fallback;
    }

    /**
     * Append " (2)", " (3)"… until the name is unused, comparing without case
     * because Windows and macOS treat "ACME" and "Acme" as the same folder.
     *
     * @param  array<string, true>  $used
     */
    private function uniqueName(string $base, array &$used, string $extension = ''): string
    {
        $suffix = $extension !== '' ? ".{$extension}" : '';
        $candidate = $base.$suffix;

        for ($n = 2; isset($used[mb_strtolower($candidate)]); $n++) {
            $candidate = "{$base} ({$n}){$suffix}";
        }

        $used[mb_strtolower($candidate)] = true;

        return $candidate;
    }

    /**
     * A zip entry holds a wall-clock time with no zone, which unzip tools read
     * as local time, so it is written as MPC's wall clock rather than UTC's.
     */
    private function zoned(?CarbonInterface $at): CarbonInterface
    {
        return ($at ?? now())->copy()->timezone(config('mpc.timezone'));
    }

    /** Stored in UTC; shown, like everywhere else in the app, in MPC's zone. */
    private function localTime(?CarbonInterface $at): string
    {
        return $at?->copy()->timezone(config('mpc.timezone'))->format('Y-m-d H:i') ?? '';
    }

    /**
     * UTF-8 with a byte-order mark so Excel shows Arabic names correctly.
     * Vendor-supplied text that starts like a formula is prefixed with an
     * apostrophe so a spreadsheet shows it rather than running it.
     *
     * @param  array<int, string>  $header
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function csv(array $header, array $rows): string
    {
        $handle = fopen('php://temp', 'w+b');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $header, escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(function ($value) {
                $value = (string) ($value ?? '');

                return preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : $value;
            }, $row), escape: '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * One access-log row per exported document and one audit row per vendor.
     * Written directly rather than through FileUploadService::logAccess(),
     * which takes the IP and user agent from the HTTP request — under artisan
     * that is a stand-in claiming 127.0.0.1 and "Symfony".
     *
     * @param  Collection<int, Vendor>  $vendors
     * @param  array<string, array{document: VendorDocument, status: string, file: string, part: int}>  $outcomes
     */
    private function recordAccess(User $user, Collection $vendors, array $outcomes): void
    {
        $included = collect($outcomes)->where('status', 'included');

        foreach ($included as $outcome) {
            DocumentAccessLog::create([
                'user_id' => $user->id,
                'vendor_id' => $outcome['document']->vendor_id,
                'document_type' => VendorDocument::class,
                'document_id' => $outcome['document']->id,
                'action' => 'exported',
                'ip_address' => null,
                'user_agent' => self::AGENT,
                'accessed_at' => now(),
            ]);
        }

        $perVendor = collect($outcomes)->groupBy(fn ($outcome) => $outcome['document']->vendor_id);

        foreach ($vendors as $vendor) {
            $mine = $perVendor->get($vendor->id, collect());

            AuditLog::create([
                'user_id' => $user->id,
                'vendor_id' => $vendor->id,
                'auditable_type' => Vendor::class,
                'auditable_id' => $vendor->id,
                'action' => 'vendor_exported',
                'new_values' => [
                    'export' => array_map('basename', $this->uploaded),
                    'documents' => $mine->where('status', 'included')->count(),
                    'missing_documents' => $mine->where('status', 'missing')->count(),
                    'unreadable_documents' => $mine->where('status', 'read error')->count(),
                ],
                'ip_address' => null,
                'user_agent' => self::AGENT,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Delete exports on the s3 disk, keeping the given ones. Only ever looks
     * under DIRECTORY. The disk does not throw (throw => false): a refused
     * delete only shows in its return value, so each is checked.
     *
     * @param  array<int, string>  $except
     * @return array{0: int, 1: array<int, string>} how many went, and the paths storage refused to delete
     */
    private function deleteExports(array $except = []): array
    {
        $disk = Storage::disk('s3');
        $old = array_values(array_diff($disk->files(self::DIRECTORY), $except));
        $left = array_values(array_filter($old, fn (string $path) => ! $disk->delete($path)));

        return [count($old) - count($left), $left];
    }

    private function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => round($bytes / 1024 ** 3, 2).' GB',
            $bytes >= 1024 ** 2 => round($bytes / 1024 ** 2, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024, 1).' KB',
            default => "{$bytes} B",
        };
    }
}
