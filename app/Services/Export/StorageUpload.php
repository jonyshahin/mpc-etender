<?php

namespace App\Services\Export;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\AwsS3V3Adapter;

/**
 * A file being written straight into storage as it is produced, so the
 * instance writing it never holds the whole file on its own disk.
 *
 * Laravel Cloud runs commands on a live web instance whose local disk is a
 * small allowance (512 MB per GB of RAM). An export staged there first
 * killed the instance; one streamed through this does not.
 */
abstract class StorageUpload
{
    /** Set once complete() has started: only from then can the file exist. */
    protected bool $finishing = false;

    /**
     * The right upload for the disk: a multipart upload straight into the
     * bucket for S3-compatible storage (Laravel Cloud, MinIO), a spooled
     * write for anything else (the local and fake disks used in development
     * and tests).
     *
     * @param  string  $path  where the file lands, relative to the disk
     */
    public static function to(Filesystem $disk, string $path, string $contentType = 'application/octet-stream'): self
    {
        if ($disk instanceof AwsS3V3Adapter) {
            return new MultipartUpload(
                client: $disk->getClient(),
                bucket: $disk->getConfig()['bucket'],
                key: $disk->path($path),
                path: $path,
                contentType: $contentType,
            );
        }

        return new SpooledUpload($disk, $path);
    }

    /** Append bytes to the file. */
    abstract public function write(string $bytes): void;

    /** How many bytes have been written so far. */
    abstract public function bytes(): int;

    /**
     * Finish the file in storage.
     *
     * @return string its path on the disk
     */
    abstract public function complete(): string;

    /**
     * Throw away whatever has been sent but not yet made into a file. Safe to
     * call at any point, and more than once; a no-op once complete() has
     * returned — removing a finished file is the caller's business.
     *
     * @return bool false if storage refused, so an unfinished upload may remain
     */
    abstract public function abort(): bool;

    /** Where the file lands, relative to the disk. */
    abstract public function path(): string;

    /**
     * Whether the file may be in storage. False until complete() is called:
     * nothing before it creates the file, so after an earlier failure there is
     * nothing at path() to remove — or to report as left behind.
     */
    public function mayExist(): bool
    {
        return $this->finishing;
    }

    /** A write-only stream over this upload, for writers that want one (ZipStream). */
    public function stream(): UploadStream
    {
        return new UploadStream($this);
    }
}
