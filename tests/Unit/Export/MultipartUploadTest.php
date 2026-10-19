<?php

use App\Services\Export\MultipartUpload;
use App\Services\Export\SpooledUpload;
use App\Services\Export\StorageUpload;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InMemoryS3;

/**
 * The upload an export streams into. Against the in-memory S3, which refuses
 * what real S3 and Cloudflare R2 refuse: parts under 5 MiB but the last, and
 * parts of differing sizes but the last.
 */
function multipartTo(InMemoryS3 $s3, string $path = 'exports/vendors/x.zip'): MultipartUpload
{
    $upload = StorageUpload::to($s3->disk(), $path, 'application/zip');
    expect($upload)->toBeInstanceOf(MultipartUpload::class);

    return $upload;
}

test('bytes written in uneven pieces arrive whole, sent in equal parts of 8 MiB with a shorter last one', function () {
    $s3 = new InMemoryS3;
    $upload = multipartTo($s3);
    $sent = '';

    // Writes of awkward sizes, as PHP's stream layer and ZipStream produce.
    foreach ([1, 8192, 3 * 1024 * 1024 + 7, 16 * 1024 * 1024, 5, 2 * 1024 * 1024] as $i => $size) {
        $chunk = str_repeat(chr(65 + $i), $size);
        $upload->write($chunk);
        $sent .= $chunk;
    }

    expect($upload->bytes())->toBe(strlen($sent))
        ->and($upload->complete())->toBe('exports/vendors/x.zip')
        ->and($s3->objects['exports/vendors/x.zip'])->toBe($sent);

    $parts = array_values(array_filter($s3->sent, fn ($c) => $c['name'] === 'UploadPart'));
    expect(array_slice(array_column($parts, 'size'), 0, -1))->each->toBe(MultipartUpload::PART_SIZE)
        ->and(end($parts)['size'])->toBe(strlen($sent) - (count($parts) - 1) * MultipartUpload::PART_SIZE)
        ->and($s3->sentNames())->toContain('CreateMultipartUpload')->toContain('CompleteMultipartUpload')
        ->not->toContain('PutObject');
});

test('parts go out as they fill, not at the end: at most one part is ever held', function () {
    $s3 = new InMemoryS3;
    $upload = multipartTo($s3);

    $upload->write(str_repeat('a', MultipartUpload::PART_SIZE * 2 + 100));

    // Two parts already sent; only the 100-byte remainder is held back.
    expect(array_count_values($s3->sentNames())['UploadPart'])->toBe(2)
        ->and($s3->objects)->toBe([]);
});

test('a file that never fills a part is sent with a single PutObject', function () {
    $s3 = new InMemoryS3;
    $upload = multipartTo($s3);

    $upload->write('small zip');
    $upload->complete();

    expect($s3->sentNames())->toBe(['PutObject'])
        ->and($s3->objects['exports/vendors/x.zip'])->toBe('small zip');
});

test('aborting leaves nothing in storage: the unfinished upload is cancelled', function () {
    $s3 = new InMemoryS3;
    $upload = multipartTo($s3);

    $upload->write(str_repeat('a', MultipartUpload::PART_SIZE + 1));
    expect($s3->uploads)->toHaveCount(1);

    $upload->abort();
    $upload->abort();

    expect($s3->uploads)->toBe([])
        ->and($s3->objects)->toBe([])
        ->and(array_count_values($s3->sentNames())['AbortMultipartUpload'])->toBe(1);
});

test('aborting before any part was sent, or after completing, sends nothing', function () {
    $s3 = new InMemoryS3;

    $early = multipartTo($s3, 'exports/vendors/early.zip');
    $early->write('a little');
    $early->abort();

    $done = multipartTo($s3, 'exports/vendors/done.zip');
    $done->write('done');
    $done->complete();
    $done->abort();

    expect($s3->sentNames())->toBe(['PutObject'])
        ->and($s3->objects)->toHaveKey('exports/vendors/done.zip');
});

test('a failing part surfaces as an exception, and abort then cleans up', function () {
    $s3 = new InMemoryS3;
    $s3->failures['UploadPart'] = fn ($command) => $command['PartNumber'] === 2 ? $s3->error($command, 'InternalError', 500) : null;
    $upload = multipartTo($s3);

    expect(fn () => $upload->write(str_repeat('a', MultipartUpload::PART_SIZE * 2)))->toThrow(Exception::class);

    $upload->abort();
    expect($s3->uploads)->toBe([])->and($s3->objects)->toBe([]);
});

test('the key carries the disk\'s root, as every other file on the disk does', function () {
    $s3 = new InMemoryS3;
    $disk = Storage::build([
        'driver' => 's3', 'key' => 't', 'secret' => 't', 'region' => 'us-east-1', 'bucket' => 'b',
        'endpoint' => 'http://s3.test', 'use_path_style_endpoint' => true, 'root' => 'tenant-a', 'handler' => $s3,
    ]);

    $upload = StorageUpload::to($disk, 'exports/vendors/x.zip');
    $upload->write('z');

    expect($upload->complete())->toBe('exports/vendors/x.zip')
        ->and(array_keys($s3->objects))->toBe(['tenant-a/exports/vendors/x.zip'])
        ->and($disk->get('exports/vendors/x.zip'))->toBe('z');
});

test('disks that are not S3 get a spooled upload that writes the file in one go', function () {
    Storage::fake('local-test');
    $upload = StorageUpload::to(Storage::disk('local-test'), 'exports/x.zip');

    expect($upload)->toBeInstanceOf(SpooledUpload::class);

    $upload->write('abc');
    $upload->write('def');

    expect($upload->complete())->toBe('exports/x.zip')
        ->and(Storage::disk('local-test')->get('exports/x.zip'))->toBe('abcdef');
});
