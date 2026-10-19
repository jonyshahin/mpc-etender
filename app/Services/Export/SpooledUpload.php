<?php

namespace App\Services\Export;

use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/**
 * For disks that cannot take a file in pieces (the local and fake disks used
 * in development and tests): bytes go to a temporary file, which is written to
 * the disk in one go on complete(). Not for production, where the s3 disk gets
 * a MultipartUpload instead.
 */
class SpooledUpload extends StorageUpload
{
    /** @var resource|null */
    private $spool;

    private int $bytes = 0;

    private bool $completed = false;

    public function __construct(
        private readonly Filesystem $disk,
        private readonly string $path,
    ) {
        $this->spool = tmpfile() ?: throw new RuntimeException('Could not open a temporary file.');
    }

    public function write(string $bytes): void
    {
        if ($this->spool === null) {
            throw new RuntimeException("{$this->path} is already complete or aborted.");
        }

        fwrite($this->spool, $bytes);
        $this->bytes += strlen($bytes);
    }

    public function bytes(): int
    {
        return $this->bytes;
    }

    public function complete(): string
    {
        if ($this->completed) {
            return $this->path;
        }

        $this->finishing = true;
        rewind($this->spool);
        $written = $this->disk->writeStream($this->path, $this->spool);
        fclose($this->spool);
        $this->spool = null;

        if ($written === false) {
            throw new RuntimeException("{$this->path} could not be written to storage.");
        }

        $this->completed = true;

        return $this->path;
    }

    public function abort(): bool
    {
        if ($this->spool !== null) {
            fclose($this->spool);
            $this->spool = null;
        }

        return true;
    }

    public function path(): string
    {
        return $this->path;
    }
}
