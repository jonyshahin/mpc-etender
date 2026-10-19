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
use App\Services\Export\MultipartUpload;
use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InMemoryS3;

uses(RefreshDatabase::class);

/**
 * The s3 disk is Laravel's real S3 driver over an in-memory store (see
 * InMemoryS3), so these run the production path: documents read with
 * GetObject, zips streamed in with multipart uploads.
 */
beforeEach(function () {
    $this->s3 = new InMemoryS3;
    Storage::set('s3', $this->s3->disk());
});

function s3(): InMemoryS3
{
    return test()->s3;
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

/**
 * A vendor document whose file really is in the bucket. Placed in the store
 * directly: the SDK's own uploader cannot send a string body of 16 MB or more
 * (it re-opens php://temp by its URI and finds it empty) — a quirk of the
 * test setup, not of production, where uploads come from real files.
 */
function storedVendorDocument(Vendor $vendor, array $attributes = [], ?string $contents = 'PDF'): VendorDocument
{
    $document = VendorDocument::factory()->create(['vendor_id' => $vendor->id] + $attributes);

    if ($contents !== null) {
        s3()->objects[$document->file_path] = $contents;
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

/** @return array<string, string> the export's zips in the bucket: key => bytes */
function exportedObjects(): array
{
    $objects = array_filter(
        s3()->objects,
        fn (string $key) => str_starts_with($key, ExportVendorsCommand::DIRECTORY.'/'),
        ARRAY_FILTER_USE_KEY,
    );
    ksort($objects, SORT_NATURAL);

    return $objects;
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

    foreach (exportedObjects() as $key => $bytes) {
        $file = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($file, $bytes);

        $zip = new ZipArchive;
        expect($zip->open($file, ZipArchive::CHECKCONS))->toBeTrue();

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $entries[$name] = str_ends_with($name, '/') ? null : $zip->getFromIndex($i);
        }
        $zip->close();
        unlink($file);

        $parts[basename($key)] = $entries;
    }

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

/** Fail the given command for keys under the export folder, as storage would refuse it. */
function refuseExportWrites(string $command, ?int $nth = null): void
{
    $count = 0;
    s3()->failures[$command] = function ($cmd) use (&$count, $nth) {
        if (! str_starts_with((string) $cmd['Key'], ExportVendorsCommand::DIRECTORY.'/')) {
            return null;
        }

        return ($nth === null || ++$count === $nth) ? s3()->error($cmd, 'AccessDenied', 403) : null;
    };
}

// ── What the export contains ────────────────────────────────────────────────

test('exports one folder per vendor holding its documents, plus a list of every vendor', function () {
    $acme = Vendor::factory()->create(['company_name' => 'Acme Steel', 'email' => 'acme@example.com']);
    $acme->categories()->attach(Category::factory()->create(['name_en' => 'Steel'])->id);
    storedVendorDocument($acme, ['document_type' => DocumentType::TradeLicense, 'title' => 'Licence 2026'], 'licence-bytes');
    storedVendorDocument($acme, ['document_type' => DocumentType::Insurance, 'title' => 'Liability cover'], 'insurance-bytes');
    Vendor::factory()->create(['company_name' => 'Babylon Concrete']);

    expect(runVendorExport())->toBe(Command::SUCCESS)
        // Small enough for one zip, which then carries no part number.
        ->and(array_keys(exportedObjects()))->toHaveCount(1)
        ->and(array_key_first(exportedObjects()))->toMatch('#^'.ExportVendorsCommand::DIRECTORY.'/vendors-export-\d{8}-\d{6}-[a-z0-9]{4}\.zip$#');

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
        ->and(array_column($documents, 'Zip file'))->each->toMatch('/^vendors-export-\d{8}-\d{6}-[a-z0-9]{4}\.zip$/');
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
        ->each->toMatch('/^vendors-export-\d{8}-\d{6}-[a-z0-9]{4}-part[123]\.zip$/');

    foreach (exportedObjects() as $bytes) {
        expect(strlen($bytes))->toBeLessThan($mb);
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

test('dates and times are given in MPC\'s zone, as the app shows them, and say so', function () {
    $vendor = Vendor::factory()->create(['qualified_at' => '2026-05-01 22:30:00']);
    $vendor->forceFill(['created_at' => '2026-04-30 21:15:00'])->save();

    runVendorExport();

    $row = csvRows(exportedZip()['vendors.csv'])[0];
    expect(config('mpc.timezone'))->toBe('Asia/Baghdad')
        ->and($row['Qualified at (Asia/Baghdad)'])->toBe('2026-05-02 01:30')
        ->and($row['Created at (Asia/Baghdad)'])->toBe('2026-05-01 00:15');
});

test('files in the zip carry MPC\'s wall-clock time, which is how unzip tools read it', function () {
    $vendor = Vendor::factory()->create(['company_name' => 'Acme']);
    $document = storedVendorDocument($vendor, ['document_type' => DocumentType::Other, 'title' => 'Scan'], 'x');
    // 00:15 on 2 May in Baghdad.
    $document->forceFill(['created_at' => '2026-05-01 21:15:00'])->save();

    runVendorExport();

    expect(zipEntryTimes(array_values(exportedObjects())[0])['Acme/Other - Scan.pdf'])->toBe('2026-05-02 00:15');
});

// ── Streaming into storage ──────────────────────────────────────────────────

test('a zip streams into storage as it is built: parts go up while documents are still being read', function () {
    $mb = 1024 * 1024;

    foreach (['Alpha', 'Bravo', 'Charlie'] as $i => $name) {
        storedVendorDocument(Vendor::factory()->create(['company_name' => $name]), ['document_type' => DocumentType::Other, 'title' => 'Scan'], str_repeat(chr(97 + $i), 6 * $mb));
    }
    s3()->sent = [];

    expect(runVendorExport())->toBe(Command::SUCCESS);

    $names = s3()->sentNames();
    $firstPart = array_search('UploadPart', $names, true);
    $lastRead = max(array_keys($names, 'GetObject', true));

    // The first 8 MiB went up before the last document was even fetched: the
    // zip was never assembled anywhere first.
    expect($firstPart)->toBeLessThan($lastRead)
        ->and($names)->toContain('CreateMultipartUpload')->toContain('CompleteMultipartUpload');

    $parts = array_values(array_filter(s3()->sent, fn ($c) => $c['name'] === 'UploadPart'));
    expect(array_slice(array_column($parts, 'size'), 0, -1))->each->toBe(MultipartUpload::PART_SIZE)
        ->and(collect(s3()->sent)->where('name', 'PutObject')->where('key', '!=', null)
            ->filter(fn ($c) => str_starts_with($c['key'], ExportVendorsCommand::DIRECTORY))->count())->toBe(0);

    $zip = exportedZip();
    expect($zip['Alpha/Other - Scan.pdf'])->toBe(str_repeat('a', 6 * $mb))
        ->and($zip['Charlie/Other - Scan.pdf'])->toBe(str_repeat('c', 6 * $mb));
});

test('the run reports its progress as it goes, so a stopped run shows how far it got', function () {
    storedVendorDocument(Vendor::factory()->create(['company_name' => 'Alpha']));
    Vendor::factory()->create(['company_name' => 'Bravo']);

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email])
        ->expectsOutputToContain('[1/2] Alpha — 1 document(s)')
        ->expectsOutputToContain('[2/2] Bravo — 0 document(s)')
        ->expectsOutputToContain('Uploaded vendors-export-')
        ->assertSuccessful();
});

test('an upload a stopped run left unfinished is cleared by the next run and by --clean', function () {
    Vendor::factory()->create();
    s3()->uploads['left-behind-1'] = ['key' => ExportVendorsCommand::DIRECTORY.'/vendors-export-old-part2.zip', 'parts' => [1 => 'x']];
    s3()->uploads['someone-else'] = ['key' => 'vendor-docs/big-upload.pdf', 'parts' => [1 => 'y']];

    runVendorExport();

    expect(array_keys(s3()->uploads))->toBe(['someone-else']);

    s3()->uploads['left-behind-2'] = ['key' => ExportVendorsCommand::DIRECTORY.'/vendors-export-older.zip', 'parts' => []];

    $this->artisan('mpc:export-vendors', ['--clean' => true])
        ->expectsOutputToContain('abandoned 1 unfinished upload')
        ->assertSuccessful();

    expect(array_keys(s3()->uploads))->toBe(['someone-else']);
});

test('on a disk that is not S3, as in local development, the export still works', function () {
    Storage::fake('s3');
    Storage::disk('s3')->buildTemporaryUrlsUsing(fn ($path) => "https://signed.test/{$path}");
    $vendor = Vendor::factory()->create(['company_name' => 'Local Co']);
    $document = VendorDocument::factory()->create(['vendor_id' => $vendor->id, 'document_type' => DocumentType::Other, 'title' => 'Doc']);
    Storage::disk('s3')->put($document->file_path, 'local-bytes');

    expect(runVendorExport())->toBe(Command::SUCCESS);

    $files = Storage::disk('s3')->files(ExportVendorsCommand::DIRECTORY);
    $zip = new ZipArchive;
    expect($files)->toHaveCount(1)
        ->and($zip->open(Storage::disk('s3')->path($files[0]), ZipArchive::CHECKCONS))->toBeTrue()
        ->and($zip->getFromName('Local Co/Other - Doc.pdf'))->toBe('local-bytes');
});

// ── Logging ─────────────────────────────────────────────────────────────────

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

// ── Documents that cannot be exported ───────────────────────────────────────

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

    // The object exists, but reading it is refused.
    s3()->failures['GetObject'] = fn ($cmd) => $cmd['Key'] === $broken->file_path ? s3()->error($cmd, 'AccessDenied', 403) : null;

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email])
        ->expectsOutputToContain('could not be read from storage')
        ->assertFailed();

    $row = collect(csvRows(exportedZip()['documents.csv']))->firstWhere('Title', 'Unreadable');
    expect($row['In export'])->toBe('read error')
        ->and(DocumentAccessLog::where('document_id', $broken->id)->exists())->toBeFalse()
        ->and(AuditLog::where('auditable_id', $vendor->id)->first()->new_values['unreadable_documents'])->toBe(1);
});

test('a document its vendor deletes while the export runs is not reported as lost from storage', function () {
    $vendor = Vendor::factory()->create();
    storedVendorDocument($vendor, ['title' => 'Kept'], 'kept');
    $gone = storedVendorDocument($vendor, ['title' => 'Withdrawn'], 'gone');

    s3()->failures['GetObject'] = function ($cmd) use ($gone) {
        if ($cmd['Key'] !== $gone->file_path) {
            return null;
        }

        // What VendorDocumentService::delete does: row and file both go.
        VendorDocument::whereKey($gone->id)->delete();
        unset(s3()->objects[$gone->file_path]);

        return s3()->error($cmd, 'NoSuchKey', 404);
    };

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email])
        ->expectsOutputToContain('deleted by their vendor while the export ran')
        ->doesntExpectOutputToContain('missing from storage')
        ->assertSuccessful();

    $row = collect(csvRows(exportedZip()['documents.csv']))->firstWhere('Title', 'Withdrawn');
    expect($row['In export'])->toBe('deleted during export')
        ->and(AuditLog::where('auditable_id', $vendor->id)->first()->new_values['missing_documents'])->toBe(0);
});

// ── When storage fails ──────────────────────────────────────────────────────

test('when storage refuses a zip, the run says why, keeps nothing, logs nothing and lets go of the lock', function () {
    foreach (['Alpha', 'Bravo'] as $name) {
        storedVendorDocument(Vendor::factory()->create(['company_name' => $name]), contents: str_repeat('a', 700 * 1024));
    }

    // The first zip goes up; the second is refused.
    refuseExportWrites('PutObject', nth: 2);

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email, '--part-size' => 1])
        ->expectsOutputToContain('The export failed')
        ->expectsOutputToContain('Nothing was logged as exported')
        ->assertFailed();

    expect(array_keys(exportedObjects()))->toBe([])
        ->and(DocumentAccessLog::count())->toBe(0)
        ->and(AuditLog::where('action', 'vendor_exported')->count())->toBe(0)
        ->and(Cache::lock('mpc:export-vendors', 1)->get())->toBeTrue();
});

test('a part refused halfway through a multipart upload is cancelled, not left half-written', function () {
    storedVendorDocument(Vendor::factory()->create(), contents: str_repeat('a', 20 * 1024 * 1024));
    refuseExportWrites('UploadPart', nth: 2);

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email])
        ->expectsOutputToContain('The export failed')
        ->assertFailed();

    expect(s3()->sentNames())->toContain('AbortMultipartUpload')
        ->and(array_keys(s3()->uploads))->toBe([])
        ->and(array_keys(exportedObjects()))->toBe([])
        ->and(DocumentAccessLog::count())->toBe(0);
});

test('zips that cannot be removed after a failure are named, with what to do about them', function () {
    foreach (['Alpha', 'Bravo'] as $name) {
        storedVendorDocument(Vendor::factory()->create(['company_name' => $name]), contents: str_repeat('a', 700 * 1024));
    }

    refuseExportWrites('PutObject', nth: 2);
    refuseExportWrites('DeleteObject');

    Artisan::call('mpc:export-vendors', ['--by' => vendorExporter()->email, '--part-size' => 1]);

    expect(Artisan::output())->toContain('The export failed')
        ->toContain('storage refused to remove these')
        ->toContain('--clean')
        ->toContain('-part1.zip')
        ->not->toContain('Nothing was logged as exported, and nothing it uploaded was kept');
});

test('a zip storage finished even though the request reported an error is removed, not left unlogged', function () {
    storedVendorDocument(Vendor::factory()->create(), contents: str_repeat('a', 20 * 1024 * 1024));

    // Storage assembles the zip — and, as real S3 and R2 do, forgets the
    // upload — then the reply is lost: the client sees an error, the file is
    // nonetheless there, and aborting the upload now answers NoSuchUpload.
    s3()->failures['CompleteMultipartUpload'] = function ($cmd) {
        $parts = s3()->uploads[$cmd['UploadId']]['parts'];
        ksort($parts);
        s3()->objects[$cmd['Key']] = implode('', $parts);
        unset(s3()->uploads[$cmd['UploadId']]);

        return s3()->error($cmd, 'AccessDenied', 403);
    };

    Artisan::call('mpc:export-vendors', ['--by' => vendorExporter()->email]);

    expect(Artisan::output())->toContain('The export failed')
        ->toContain('nothing it uploaded was kept')
        ->not->toContain('refused to remove')
        ->and(array_keys(exportedObjects()))->toBe([])
        ->and(array_keys(s3()->uploads))->toBe([])
        ->and(DocumentAccessLog::count())->toBe(0);
});

test('a zip that was never finished is not named as left behind, even when deleting its key fails', function () {
    storedVendorDocument(Vendor::factory()->create(), contents: str_repeat('a', 20 * 1024 * 1024));
    refuseExportWrites('UploadPart', nth: 2);
    refuseExportWrites('DeleteObject');

    Artisan::call('mpc:export-vendors', ['--by' => vendorExporter()->email]);

    // Nothing can exist at the key: only finishing the upload creates it.
    expect(Artisan::output())->toContain('nothing it uploaded was kept')
        ->not->toContain('refused to remove')
        ->and(array_keys(exportedObjects()))->toBe([]);
});

test('--clean fails, naming it, when it cannot cancel an unfinished upload, and still tries the rest', function () {
    s3()->uploads['stuck'] = ['key' => ExportVendorsCommand::DIRECTORY.'/vendors-export-a.zip', 'parts' => []];
    s3()->uploads['fine'] = ['key' => ExportVendorsCommand::DIRECTORY.'/vendors-export-b.zip', 'parts' => []];
    s3()->failures['AbortMultipartUpload'] = fn ($cmd) => $cmd['UploadId'] === 'stuck' ? s3()->error($cmd, 'AccessDenied', 403) : null;

    $code = Artisan::call('mpc:export-vendors', ['--clean' => true]);

    expect($code)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('an unfinished upload of exports/vendors/vendors-export-a.zip')
        ->and(array_keys(s3()->uploads))->toBe(['stuck']);

    unset(s3()->failures['AbortMultipartUpload']);

    expect(Artisan::call('mpc:export-vendors', ['--clean' => true]))->toBe(Command::SUCCESS)
        ->and(s3()->uploads)->toBe([]);
});

test('a run in the same second as an earlier export cannot touch that export\'s zip', function () {
    Vendor::factory()->create();
    $officer = vendorExporter();
    $this->freezeTime();

    runVendorExport(by: $officer);
    $earlier = array_keys(exportedObjects());

    // Same second; this run's upload is refused, so it cleans up after itself.
    refuseExportWrites('PutObject');
    runVendorExport(by: $officer);

    expect($earlier)->toHaveCount(1)
        ->and(array_keys(exportedObjects()))->toBe($earlier);
});

test('cleaning up after a failure does not depend on error reporting working', function () {
    foreach (['Alpha', 'Bravo'] as $name) {
        storedVendorDocument(Vendor::factory()->create(['company_name' => $name]), contents: str_repeat('a', 700 * 1024));
    }
    refuseExportWrites('PutObject', nth: 2);

    // A broken log channel: reporting the failure itself throws.
    app(ExceptionHandler::class)->reportable(function (Throwable $e) {
        throw new UnexpectedValueException('The stream or file "laravel.log" could not be opened');
    });

    try {
        Artisan::call('mpc:export-vendors', ['--by' => vendorExporter()->email, '--part-size' => 1]);
    } catch (UnexpectedValueException) {
    }

    expect(array_keys(exportedObjects()))->toBe([])
        ->and(Artisan::output())->toContain('nothing it uploaded was kept');
});

test('when the unfinished upload cannot be cancelled either, the run says so instead of claiming nothing was kept', function () {
    storedVendorDocument(Vendor::factory()->create(), contents: str_repeat('a', 20 * 1024 * 1024));
    refuseExportWrites('UploadPart', nth: 2);
    refuseExportWrites('AbortMultipartUpload');

    Artisan::call('mpc:export-vendors', ['--by' => vendorExporter()->email]);

    expect(Artisan::output())->toContain('storage refused to remove these')
        ->toContain('an unfinished upload of exports/vendors/vendors-export-')
        ->not->toContain('nothing it uploaded was kept');
});

test('--clean says so, and fails, when storage refuses to delete an export', function () {
    Storage::disk('s3')->put(ExportVendorsCommand::DIRECTORY.'/vendors-export-old.zip', 'x');
    refuseExportWrites('DeleteObject');

    Artisan::call('mpc:export-vendors', ['--clean' => true]);

    expect(Artisan::output())->toContain('Removed 0 export file(s)')
        ->toContain('refused to delete')
        ->toContain('vendors-export-old.zip')
        ->and(exportedObjects())->toHaveKey(ExportVendorsCommand::DIRECTORY.'/vendors-export-old.zip');

    $this->artisan('mpc:export-vendors', ['--clean' => true])->assertFailed();
});

test('an older export storage refuses to delete is named after a successful run', function () {
    Vendor::factory()->create();
    Storage::disk('s3')->put(ExportVendorsCommand::DIRECTORY.'/vendors-export-older.zip', 'x');
    refuseExportWrites('DeleteObject');

    Artisan::call('mpc:export-vendors', ['--by' => vendorExporter()->email]);

    expect(Artisan::output())->toContain('An earlier export could not be removed')
        ->toContain('vendors-export-older.zip')
        ->toContain('Download links')
        ->and(AuditLog::where('action', 'vendor_exported')->count())->toBe(1);
});

test('failing to remove an older export does not undo a new export that is already logged', function () {
    Vendor::factory()->create();
    Storage::disk('s3')->put(ExportVendorsCommand::DIRECTORY.'/vendors-export-older.zip', 'x');
    s3()->failures['ListObjectsV2'] = fn ($cmd) => s3()->error($cmd, 'InternalError', 400);

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email])
        ->expectsOutputToContain('could not be removed from storage')
        ->assertSuccessful();

    expect(exportedObjects())->toHaveCount(2)
        ->and(AuditLog::where('action', 'vendor_exported')->count())->toBe(1);
});

// ── Running it ──────────────────────────────────────────────────────────────

test('the run prints links that expire when asked', function () {
    Vendor::factory()->create();

    Artisan::call('mpc:export-vendors', ['--by' => vendorExporter()->email, '--expires' => 45]);

    // Signed against the real clock, so allow for the seconds the run took.
    expect(preg_match('/X-Amz-Expires=(\d+)/', Artisan::output(), $match))->toBe(1)
        ->and((int) $match[1])->toBeGreaterThan(45 * 60 - 60)
        ->toBeLessThanOrEqual(45 * 60);
});

test('each export replaces the previous one, and --clean removes it', function () {
    Vendor::factory()->create();
    $officer = vendorExporter();

    runVendorExport(by: $officer);
    $this->travel(2)->seconds();
    runVendorExport(by: $officer);

    expect(exportedObjects())->toHaveCount(1);

    $this->artisan('mpc:export-vendors', ['--clean' => true])->assertSuccessful();

    expect(array_keys(exportedObjects()))->toBe([]);
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

    expect(array_keys(exportedObjects()))->toBe([]);
});

test('a killed run\'s lock expires shortly after Laravel Cloud\'s 30-minute command limit, not an hour later', function () {
    expect(ExportVendorsCommand::LOCK_SECONDS)->toBeGreaterThan(30 * 60)
        ->toBeLessThanOrEqual(40 * 60);
});

test('a dry run reports the size of the export, and writes and records nothing', function () {
    storedVendorDocument(Vendor::factory()->create());
    s3()->sent = [];

    $this->artisan('mpc:export-vendors', ['--by' => vendorExporter()->email, '--dry-run' => true])
        ->expectsOutputToContain('Largest document')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(array_keys(exportedObjects()))->toBe([])
        ->and(array_intersect(s3()->sentNames(), ['PutObject', 'CreateMultipartUpload', 'GetObject']))->toBe([])
        ->and(DocumentAccessLog::count())->toBe(0)
        ->and(AuditLog::where('action', 'vendor_exported')->count())->toBe(0);
});

test('the run gives itself enough memory, whatever PHP\'s default', function () {
    Vendor::factory()->create();
    $before = ini_get('memory_limit');
    ini_set('memory_limit', '128M');

    try {
        runVendorExport();
        expect(ini_parse_quantity(ini_get('memory_limit')))->toBeGreaterThanOrEqual(ini_parse_quantity('256M'));
    } finally {
        ini_set('memory_limit', $before);
    }
});

// ── Who may export ──────────────────────────────────────────────────────────

test('the export is refused unless the named user could open vendor documents in the app', function (Closure $options, string $message) {
    storedVendorDocument(Vendor::factory()->create());

    $this->artisan('mpc:export-vendors', $options())
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(array_keys(exportedObjects()))->toBe([])
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
