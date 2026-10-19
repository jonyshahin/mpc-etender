<?php

use App\Console\Commands\ExportVendorsCommand;
use App\Enums\DocumentType;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\DocumentAccessLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDocument;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expires) => "https://signed.test/{$path}?expires={$expires->getTimestamp()}",
    );
});

afterEach(function () {
    foreach (glob(storage_path('app/private/vendor-export-*'), GLOB_ONLYDIR) ?: [] as $dir) {
        File::deleteDirectory($dir);
    }
});

/** Run the command as if the instance had this much free disk. */
function withFreeDisk(Closure $bytes): void
{
    app()->bind(ExportVendorsCommand::class, fn () => new class($bytes) extends ExportVendorsCommand
    {
        public function __construct(private Closure $bytes)
        {
            parent::__construct();
        }

        protected function freeDiskBytes(): float|false
        {
            return ($this->bytes)();
        }
    });
}

/**
 * Put a spy in front of the fake s3 disk; unmocked calls reach the real one.
 *
 * @return array{0: FilesystemAdapter, 1: MockInterface}
 */
function spiedDisk(): array
{
    $disk = Storage::disk('s3');
    $spy = Mockery::mock($disk)->makePartial();
    Storage::set('s3', $spy);

    return [$disk, $spy];
}

/** The first part uploads; the second fails as the s3 disk reports it (false). */
function failSecondUpload(FilesystemAdapter $disk, MockInterface $spy): void
{
    $calls = 0;
    $spy->shouldReceive('putFileAs')->andReturnUsing(function (...$args) use ($disk, &$calls) {
        return ++$calls === 2 ? false : $disk->putFileAs(...$args);
    });
}

/** What a killed run leaves on the instance's disk. */
function leftoverWorkDir(int $bytes): string
{
    $dir = storage_path('app/private/vendor-export-KILLEDRUN01');
    File::ensureDirectoryExists($dir);
    file_put_contents("{$dir}/part-3.zip", str_repeat('x', $bytes));

    return $dir;
}

/**
 * The DOS date-time each zip entry carries, exactly as written (unzip tools
 * read it as local time): entry name => 'Y-m-d H:i'.
 *
 * @return array<string, string>
 */
function zipEntryTimes(string $bytes): array
{
    $times = [];
    $offset = 0;

    while (($offset = strpos($bytes, "PK\x01\x02", $offset)) !== false) {
        $dos = unpack('vtime/vdate', substr($bytes, $offset + 12, 4));
        $length = unpack('vname', substr($bytes, $offset + 28, 2))['name'];
        $times[substr($bytes, $offset + 46, $length)] = sprintf(
            '%04d-%02d-%02d %02d:%02d',
            (($dos['date'] >> 9) & 0x7F) + 1980, ($dos['date'] >> 5) & 0x0F, $dos['date'] & 0x1F,
            ($dos['time'] >> 11) & 0x1F, ($dos['time'] >> 5) & 0x3F,
        );
        $offset += 46;
    }

    return $times;
}

/**
 * An MPC user who can open vendor documents in the app: a real admin-group
 * role slug (the factory's slugs are deliberately random) plus vendors.view.
 */
function vendorExporter(string $slug = 'admin', array $permissions = ['vendors.view'], array $attributes = []): User
{
    $role = Role::firstOrCreate(['slug' => $slug], ['name' => str($slug)->headline(), 'is_system' => true]);

    foreach ($permissions as $permission) {
        $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['slug' => $permission], ['name' => $permission, 'module' => 'vendors'])->id,
        ]);
    }

    return User::factory()->create(['role_id' => $role->id] + $attributes);
}

/** A vendor document whose file really is in the (fake) bucket. */
function storedVendorDocument(Vendor $vendor, array $attributes = [], ?string $contents = 'PDF'): VendorDocument
{
    $document = VendorDocument::factory()->create(['vendor_id' => $vendor->id] + $attributes);

    if ($contents !== null) {
        Storage::disk('s3')->put($document->file_path, $contents);
        $document->update(['file_size' => strlen($contents)]);
    }

    return $document;
}

/** Run the export as an allowed user; returns the command's exit code. */
function runVendorExport(array $options = [], ?User $by = null): int
{
    $by ??= vendorExporter();

    return test()->artisan('mpc:export-vendors', ['--by' => $by->email] + $options)->run();
}

/**
 * Every zip in the bucket, opened, as zip name => (entry name => contents);
 * directories map to null.
 *
 * @return array<string, array<string, string|null>>
 */
function exportedParts(): array
{
    $parts = [];

    foreach (Storage::disk('s3')->files(ExportVendorsCommand::DIRECTORY) as $file) {
        $zip = new ZipArchive;
        expect($zip->open(Storage::disk('s3')->path($file), ZipArchive::CHECKCONS))->toBeTrue();

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $entries[$name] = str_ends_with($name, '/') ? null : $zip->getFromIndex($i);
        }
        $zip->close();

        $parts[basename($file)] = $entries;
    }

    ksort($parts, SORT_NATURAL);

    return $parts;
}

/** The export, which must be a single zip. */
function exportedZip(): array
{
    $parts = exportedParts();
    expect($parts)->toHaveCount(1);

    return array_values($parts)[0];
}

/** @return array<int, array<string, string>> rows keyed by header */
function csvRows(string $csv): array
{
    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    // Parsed as a stream: addresses span lines inside quoted fields.
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, substr($csv, 3));
    rewind($stream);

    $header = fgetcsv($stream, escape: '');
    $rows = [];
    while (($line = fgetcsv($stream, escape: '')) !== false) {
        $rows[] = array_combine($header, $line);
    }
    fclose($stream);

    return $rows;
}

test('exports one folder per vendor holding its documents, plus a list of every vendor', function () {
    $acme = Vendor::factory()->create(['company_name' => 'Acme Steel', 'email' => 'acme@example.com']);
    $acme->categories()->attach(Category::factory()->create(['name_en' => 'Steel'])->id);
    storedVendorDocument($acme, ['document_type' => DocumentType::TradeLicense, 'title' => 'Licence 2026'], 'licence-bytes');
    storedVendorDocument($acme, ['document_type' => DocumentType::Insurance, 'title' => 'Liability cover'], 'insurance-bytes');
    Vendor::factory()->create(['company_name' => 'Babylon Concrete']);

    expect(runVendorExport())->toBe(Command::SUCCESS)
        // Small enough for one zip, which then carries no part number.
        ->and(Storage::disk('s3')->files(ExportVendorsCommand::DIRECTORY)[0])
        ->toMatch('#^'.ExportVendorsCommand::DIRECTORY.'/vendors-export-\d{8}-\d{6}\.zip$#');

    $zip = exportedZip();
    expect($zip)
        ->toHaveKey('Acme Steel/')
        ->toHaveKey('Babylon Concrete/')
        ->and($zip['Acme Steel/Trade License - Licence 2026.pdf'])->toBe('licence-bytes')
        ->and($zip['Acme Steel/Insurance - Liability cover.pdf'])->toBe('insurance-bytes');

    $vendors = collect(csvRows($zip['vendors.csv']))->keyBy('Company name');
    expect($vendors)->toHaveCount(2)
        ->and($vendors['Acme Steel']['Email'])->toBe('acme@example.com')
        ->and($vendors['Acme Steel']['Folder'])->toBe('Acme Steel')
        ->and($vendors['Acme Steel']['Categories'])->toBe('Steel')
        ->and($vendors['Acme Steel']['Documents'])->toBe('2')
        ->and($vendors['Babylon Concrete']['Documents'])->toBe('0')
        // The list is for people: no credentials, hashed or otherwise.
        ->and($zip['vendors.csv'])->not->toContain($acme->getAuthPassword());

    $documents = csvRows($zip['documents.csv']);
    expect($documents)->toHaveCount(2)
        ->and(array_column($documents, 'In export'))->each->toBe('included')
        ->and(array_column($documents, 'Zip file'))->each->toMatch('/^vendors-export-\d{8}-\d{6}\.zip$/');
});

test('a large export is split into parts that each stay under the size limit, keeping a vendor together', function () {
    $mb = 1024 * 1024;
    $names = ['Alpha Steel', 'Bravo Cement', 'Charlie Cables'];

    foreach ($names as $name) {
        $vendor = Vendor::factory()->create(['company_name' => $name]);
        storedVendorDocument($vendor, ['document_type' => DocumentType::TradeLicense, 'title' => 'Licence'], str_repeat('a', (int) (0.3 * $mb)));
        storedVendorDocument($vendor, ['document_type' => DocumentType::Insurance, 'title' => 'Cover'], str_repeat('b', (int) (0.3 * $mb)));
    }

    expect(runVendorExport(['--part-size' => 1]))->toBe(Command::SUCCESS);

    $parts = exportedParts();
    expect(array_keys($parts))->toHaveCount(3)
        ->each->toMatch('/^vendors-export-\d{8}-\d{6}-part[123]\.zip$/');

    foreach (Storage::disk('s3')->files(ExportVendorsCommand::DIRECTORY) as $file) {
        expect(Storage::disk('s3')->size($file))->toBeLessThan($mb);
    }

    // Each vendor's two documents sit in the same part, under its folder.
    foreach (array_values($parts) as $i => $entries) {
        $folder = $names[$i];
        expect($entries)->toHaveKey("{$folder}/Trade License - Licence.pdf")
            ->toHaveKey("{$folder}/Insurance - Cover.pdf");
    }

    // The lists travel in the last part, and say where every file went.
    $last = array_values($parts)[2];
    expect(array_values($parts)[0])->not->toHaveKey('documents.csv')
        ->and($last)->toHaveKey('vendors.csv');

    $where = collect(csvRows($last['documents.csv']))->groupBy('Company name')->map(fn ($rows) => $rows->pluck('Zip file')->unique()->all());
    expect($where['Alpha Steel'][0])->toEndWith('-part1.zip')
        ->and($where['Charlie Cables'][0])->toEndWith('-part3.zip');
});

test('every exported file and every vendor is recorded against the user who took the export, as a console run', function () {
    $officer = vendorExporter();
    $vendor = Vendor::factory()->create();
    $documents = collect([storedVendorDocument($vendor), storedVendorDocument($vendor)]);
    $empty = Vendor::factory()->create();

    runVendorExport(by: $officer);

    $logs = DocumentAccessLog::all();
    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('document_id')->sort()->values()->all())->toBe($documents->pluck('id')->sort()->values()->all())
        ->and($logs->pluck('action')->unique()->all())->toBe(['exported'])
        ->and($logs->pluck('user_id')->unique()->all())->toBe([$officer->id])
        ->and($logs->pluck('vendor_id')->unique()->all())->toBe([$vendor->id])
        // Not the 127.0.0.1 / "Symfony" stand-in request artisan runs under.
        ->and($logs->pluck('ip_address')->unique()->all())->toBe([null])
        ->and($logs->pluck('user_agent')->unique()->all())->toBe(['artisan mpc:export-vendors']);

    $audits = AuditLog::where('action', 'vendor_exported')->get()->keyBy('auditable_id');
    expect($audits)->toHaveCount(2)
        ->and($audits[$vendor->id]->user_id)->toBe($officer->id)
        ->and($audits[$vendor->id]->new_values['documents'])->toBe(2)
        ->and($audits[$empty->id]->new_values['documents'])->toBe(0)
        ->and($audits[$vendor->id]->user_agent)->toBe('artisan mpc:export-vendors');
});

test('a document missing from storage is listed as missing and does not stop the export', function () {
    $vendor = Vendor::factory()->create(['company_name' => 'Tigris Pipes']);
    storedVendorDocument($vendor, ['document_type' => DocumentType::TaxCertificate, 'title' => 'Tax 2025'], 'tax');
    $lost = storedVendorDocument($vendor, ['document_type' => DocumentType::BankReference, 'title' => 'Bank letter'], contents: null);

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email])
        ->expectsOutputToContain('missing from storage')
        ->assertSuccessful();

    $zip = exportedZip();
    expect($zip)->toHaveKey('Tigris Pipes/Tax Certificate - Tax 2025.pdf')
        ->not->toHaveKey('Tigris Pipes/Bank Reference - Bank letter.pdf');

    $missing = collect(csvRows($zip['documents.csv']))->firstWhere('Title', 'Bank letter');
    expect($missing['In export'])->toBe('missing')
        ->and($missing['File'])->toBe('')
        ->and($missing['Zip file'])->toBe('')
        // Nothing left storage for it, so nothing is logged for it.
        ->and(DocumentAccessLog::where('document_id', $lost->id)->exists())->toBeFalse();
});

test('a file that is in storage but cannot be read is reported as a read error, not as missing, and fails the run', function () {
    $vendor = Vendor::factory()->create(['company_name' => 'Euphrates Glass']);
    storedVendorDocument($vendor, ['title' => 'Readable'], 'ok');
    $broken = storedVendorDocument($vendor, ['title' => 'Unreadable'], 'still here');

    // The object exists, but every read of it fails — a permissions or network
    // error, which the s3 disk (throw => false) reports as null.
    $disk = Storage::disk('s3');
    $flaky = Mockery::mock($disk)->makePartial();
    $flaky->shouldReceive('readStream')->andReturnUsing(fn (string $path) => $path === $broken->file_path ? null : $disk->readStream($path));
    Storage::set('s3', $flaky);

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email])
        ->expectsOutputToContain('could not be read from storage')
        ->assertFailed();

    $row = collect(csvRows(exportedZip()['documents.csv']))->firstWhere('Title', 'Unreadable');
    expect($row['In export'])->toBe('read error')
        ->and(DocumentAccessLog::where('document_id', $broken->id)->exists())->toBeFalse()
        ->and(AuditLog::where('auditable_id', $vendor->id)->first()->new_values['unreadable_documents'])->toBe(1);
});

test('dates and times are given in MPC\'s zone, as the app shows them, and say so', function () {
    $vendor = Vendor::factory()->create(['qualified_at' => '2026-05-01 22:30:00']);
    $vendor->forceFill(['created_at' => '2026-04-30 21:15:00'])->save();

    runVendorExport();

    $row = csvRows(exportedZip()['vendors.csv'])[0];
    expect(config('mpc.timezone'))->toBe('Asia/Baghdad')
        ->and($row['Qualified at (Asia/Baghdad)'])->toBe('2026-05-02 01:30')
        ->and($row['Created at (Asia/Baghdad)'])->toBe('2026-05-01 00:15');
});

test('names that clash, or hold characters a path cannot take, still get distinct, usable folders and files', function () {
    $first = Vendor::factory()->create(['company_name' => 'Al-Rafidain Co. Ltd']);
    Vendor::factory()->create(['company_name' => 'AL-RAFIDAIN CO. LTD']);
    Vendor::factory()->create(['company_name' => 'Nour <Trading>: "North/South"?']);
    Vendor::factory()->create(['company_name' => 'شركة النور']);
    // Would otherwise put a folder where the root list must go.
    Vendor::factory()->create(['company_name' => 'Documents.CSV']);
    storedVendorDocument($first, ['document_type' => DocumentType::Other, 'title' => 'Scan.pdf'], 'one');
    storedVendorDocument($first, ['document_type' => DocumentType::Other, 'title' => 'scan'], 'two');

    runVendorExport();

    $zip = exportedZip();
    $folders = array_keys(array_filter($zip, fn ($contents) => $contents === null));

    // Windows and macOS would merge the first two into one folder. Which of
    // them gets the suffix depends on the database's collation.
    expect(array_map('mb_strtolower', $folders))->toEqualCanonicalizing([
        'al-rafidain co. ltd/',
        'al-rafidain co. ltd (2)/',
        'nour -trading- -north-south/',
        'شركة النور/',
        'documents.csv (2)/',
    ])->and($zip)->toHaveKey('documents.csv');

    $firstFolder = collect(csvRows($zip['vendors.csv']))->firstWhere('Company name', 'Al-Rafidain Co. Ltd')['Folder'];
    expect(array_keys($zip))->toContain("{$firstFolder}/Other - Scan.pdf")
        ->toContain("{$firstFolder}/Other - scan (2).pdf");
});

test('text a spreadsheet would run as a formula is shown as text', function () {
    Vendor::factory()->create(['company_name' => '=HYPERLINK("http://evil.test","Click")']);

    runVendorExport();

    $row = csvRows(exportedZip()['vendors.csv'])[0];
    expect($row['Company name'])->toBe('\'=HYPERLINK("http://evil.test","Click")');
});

test('the run prints a link that expires when asked', function () {
    Vendor::factory()->create();

    $this->travelTo(now()->startOfMinute());

    // One line carries the whole link, and a line satisfies one expectation.
    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email, '--expires' => 45])
        ->expectsOutputToContain('.zip?expires='.now()->addMinutes(45)->getTimestamp())
        ->assertSuccessful();
});

test('each export replaces the previous one, and --clean removes it', function () {
    Vendor::factory()->create();
    $officer = vendorExporter();

    runVendorExport(by: $officer);
    $this->travel(2)->seconds();
    runVendorExport(by: $officer);

    expect(Storage::disk('s3')->files(ExportVendorsCommand::DIRECTORY))->toHaveCount(1);

    $this->artisan('mpc:export-vendors', ['--clean' => true])->assertSuccessful();

    expect(Storage::disk('s3')->files(ExportVendorsCommand::DIRECTORY))->toBe([]);
});

test('--clean only ever touches the export folder', function () {
    Storage::disk('s3')->put('vendor-docs/keep.pdf', 'x');
    Storage::disk('s3')->put(ExportVendorsCommand::DIRECTORY.'/vendors-export-old.zip', 'x');

    $this->artisan('mpc:export-vendors', ['--clean' => true])->assertSuccessful();

    expect(Storage::disk('s3')->exists('vendor-docs/keep.pdf'))->toBeTrue()
        ->and(Storage::disk('s3')->exists(ExportVendorsCommand::DIRECTORY.'/vendors-export-old.zip'))->toBeFalse();
});

test('a second export is refused while one is running', function () {
    Vendor::factory()->create();
    Cache::lock('mpc:export-vendors', 60)->get();

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email])
        ->expectsOutputToContain('Another vendor export is running')
        ->assertFailed();

    expect(Storage::disk('s3')->files(ExportVendorsCommand::DIRECTORY))->toBe([]);
});

test('a dry run reports the size of the export and the disk it needs, and writes and records nothing', function () {
    storedVendorDocument(Vendor::factory()->create());

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email, '--dry-run' => true])
        ->expectsOutputToContain('Local disk needed')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Storage::disk('s3')->files(ExportVendorsCommand::DIRECTORY))->toBe([])
        ->and(DocumentAccessLog::count())->toBe(0)
        ->and(AuditLog::where('action', 'vendor_exported')->count())->toBe(0);
});

// ── The instance's disk ─────────────────────────────────────────────────────

test('the export is refused, before anything is written, when the instance has too little free disk', function () {
    storedVendorDocument(Vendor::factory()->create());
    withFreeDisk(fn () => 1.0);

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email])
        ->expectsOutputToContain('smaller --part-size')
        ->assertFailed();

    expect(Storage::disk('s3')->files(ExportVendorsCommand::DIRECTORY))->toBe([])
        ->and(DocumentAccessLog::count())->toBe(0);
});

test('a small export does not demand a whole part\'s worth of disk', function () {
    storedVendorDocument(Vendor::factory()->create());

    // Well under the default 250 MB part, yet plenty for a few bytes.
    withFreeDisk(fn () => 40.0 * 1024 * 1024);

    expect(runVendorExport())->toBe(Command::SUCCESS);
});

test('a killed run\'s leftover work folder is removed before free disk is measured', function () {
    storedVendorDocument(Vendor::factory()->create());
    $leftover = leftoverWorkDir(5 * 1024 * 1024);

    // 36 MB in all: enough for this export (about 32 MB with headroom) only
    // once the 5 MB the killed run left behind is cleared.
    withFreeDisk(function () {
        $used = collect(glob(storage_path('app/private/vendor-export-*/*')) ?: [])->sum(fn ($file) => filesize($file));

        return 36.0 * 1024 * 1024 - $used;
    });

    expect(runVendorExport())->toBe(Command::SUCCESS)
        ->and(is_dir($leftover))->toBeFalse();
});

test('--clean also removes a killed run\'s leftover work folder', function () {
    $leftover = leftoverWorkDir(1024);

    $this->artisan('mpc:export-vendors', ['--clean' => true])
        ->expectsOutputToContain('1 leftover local work folder')
        ->assertSuccessful();

    expect(is_dir($leftover))->toBeFalse();
});

test('only one zip is on the instance\'s disk at a time: each is uploaded and deleted before the next', function () {
    foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
        storedVendorDocument(Vendor::factory()->create(['company_name' => $name]), contents: str_repeat('a', 700 * 1024));
    }

    [$disk, $spy] = spiedDisk();
    $onDisk = [];
    $spy->shouldReceive('putFileAs')->andReturnUsing(function (...$args) use ($disk, &$onDisk) {
        $onDisk[] = count(glob(storage_path('app/private/vendor-export-*/*.zip')) ?: []);

        return $disk->putFileAs(...$args);
    });

    expect(runVendorExport(['--part-size' => 1]))->toBe(Command::SUCCESS)
        ->and($onDisk)->toBe([1, 1, 1]);
});

// ── Failures ────────────────────────────────────────────────────────────────

test('when a part fails to upload, the parts already uploaded are removed, nothing is logged, and the lock is let go', function () {
    foreach (['Alpha', 'Bravo'] as $name) {
        storedVendorDocument(Vendor::factory()->create(['company_name' => $name]), contents: str_repeat('a', 700 * 1024));
    }

    [$disk, $spy] = spiedDisk();
    failSecondUpload($disk, $spy);

    expect(fn () => $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email, '--part-size' => 1])->run())
        ->toThrow(RuntimeException::class, 'could not be uploaded');

    expect($disk->files(ExportVendorsCommand::DIRECTORY))->toBe([])
        ->and(DocumentAccessLog::count())->toBe(0)
        ->and(AuditLog::where('action', 'vendor_exported')->count())->toBe(0)
        ->and(Cache::lock('mpc:export-vendors', 1)->get())->toBeTrue();
});

test('parts that cannot be removed after a failure are named, with what to do about them', function () {
    foreach (['Alpha', 'Bravo'] as $name) {
        storedVendorDocument(Vendor::factory()->create(['company_name' => $name]), contents: str_repeat('a', 700 * 1024));
    }

    [$disk, $spy] = spiedDisk();
    failSecondUpload($disk, $spy);
    $spy->shouldReceive('delete')->andReturn(false);

    try {
        Artisan::call('mpc:export-vendors', ['--by' => vendorExporter()->email, '--part-size' => 1]);
    } catch (RuntimeException) {
        // Expected: the upload failure is rethrown once the leftovers are reported.
    }

    expect(Artisan::output())->toContain('could not be removed')
        ->toContain('--clean')
        ->toContain('-part1.zip');
});

test('failing to remove an older export does not undo a new export that is already logged', function () {
    Vendor::factory()->create();
    Storage::disk('s3')->put(ExportVendorsCommand::DIRECTORY.'/vendors-export-older.zip', 'x');

    [$disk, $spy] = spiedDisk();
    $spy->shouldReceive('files')->andThrow(new RuntimeException('Unable to list contents for exports/vendors'));

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email])
        ->expectsOutputToContain('could not be removed from storage')
        ->assertSuccessful();

    expect($disk->files(ExportVendorsCommand::DIRECTORY))->toHaveCount(2)
        ->and(AuditLog::where('action', 'vendor_exported')->count())->toBe(1);
});

test('a document its vendor deletes while the export runs is not reported as lost from storage', function () {
    $vendor = Vendor::factory()->create();
    storedVendorDocument($vendor, ['title' => 'Kept'], 'kept');
    $gone = storedVendorDocument($vendor, ['title' => 'Withdrawn'], 'gone');

    [$disk, $spy] = spiedDisk();
    $spy->shouldReceive('readStream')->andReturnUsing(function (string $path) use ($disk, $gone) {
        if ($path === $gone->file_path) {
            // What VendorDocumentService::delete does: row and file both go.
            $gone->delete();
            $disk->delete($path);

            return null;
        }

        return $disk->readStream($path);
    });

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email])
        ->expectsOutputToContain('deleted by their vendor while the export ran')
        ->doesntExpectOutputToContain('missing from storage')
        ->assertSuccessful();

    $row = collect(csvRows(exportedZip()['documents.csv']))->firstWhere('Title', 'Withdrawn');
    expect($row['In export'])->toBe('deleted during export')
        ->and(AuditLog::where('auditable_id', $vendor->id)->first()->new_values['missing_documents'])->toBe(0);
});

test('a killed run\'s lock expires shortly after Laravel Cloud\'s 30-minute command limit, not an hour later', function () {
    expect(ExportVendorsCommand::LOCK_SECONDS)->toBeGreaterThan(30 * 60)
        ->toBeLessThanOrEqual(40 * 60);
});

test('files in the zip carry MPC\'s wall-clock time, which is how unzip tools read it', function () {
    $vendor = Vendor::factory()->create(['company_name' => 'Acme']);
    $document = storedVendorDocument($vendor, ['document_type' => DocumentType::Other, 'title' => 'Scan'], 'x');
    // 00:15 on 2 May in Baghdad.
    $document->forceFill(['created_at' => '2026-05-01 21:15:00'])->save();

    runVendorExport();

    $zip = Storage::disk('s3')->get(Storage::disk('s3')->files(ExportVendorsCommand::DIRECTORY)[0]);
    expect(zipEntryTimes($zip)['Acme/Other - Scan.pdf'])->toBe('2026-05-02 00:15');
});

// ── Who may export ──────────────────────────────────────────────────────────

test('the export is refused unless the named user could open vendor documents in the app', function (Closure $options, string $message) {
    storedVendorDocument(Vendor::factory()->create());

    $this->artisan('mpc:export-vendors', $options())
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(Storage::disk('s3')->files(ExportVendorsCommand::DIRECTORY))->toBe([])
        ->and(DocumentAccessLog::count())->toBe(0);
})->with([
    'nobody named' => [fn () => [], 'Name the MPC user'],
    'unknown email' => [fn () => ['--by' => 'nobody@example.com'], 'No active MPC user'],
    'inactive admin' => [fn () => ['--by' => vendorExporter(attributes: ['is_active' => false])->email], 'No active MPC user'],
    // Granted vendors.view by RolePermissionSeeder, but kept out of the admin
    // screens by role:admin,super_admin.
    'project manager with vendors.view' => [fn () => ['--by' => vendorExporter('project_manager')->email], 'cannot open vendor documents'],
    'procurement officer with vendors.view' => [fn () => ['--by' => vendorExporter('procurement_officer')->email], 'cannot open vendor documents'],
    'admin without vendors.view' => [fn () => ['--by' => vendorExporter(permissions: [])->email], 'cannot open vendor documents'],
    'admin with an unverified email' => [fn () => ['--by' => vendorExporter(attributes: ['email_verified_at' => null])->email], 'cannot open vendor documents'],
    'link life out of range' => [fn () => ['--by' => vendorExporter()->email, '--expires' => 0], '--expires must be'],
    'part size out of range' => [fn () => ['--by' => vendorExporter()->email, '--part-size' => 0], '--part-size must be'],
]);
