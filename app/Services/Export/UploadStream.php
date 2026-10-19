<?php

namespace App\Services\Export;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * The write end of a StorageUpload, as a PSR-7 stream. Write-only and
 * forward-only: every write goes straight to the upload.
 */
class UploadStream implements StreamInterface
{
    public function __construct(private readonly StorageUpload $upload) {}

    public function write(string $string): int
    {
        $this->upload->write($string);

        return strlen($string);
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function tell(): int
    {
        return $this->upload->bytes();
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function eof(): bool
    {
        return false;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('An upload stream cannot seek.');
    }

    public function rewind(): void
    {
        throw new RuntimeException('An upload stream cannot rewind.');
    }

    public function isReadable(): bool
    {
        return false;
    }

    public function read(int $length): string
    {
        throw new RuntimeException('An upload stream cannot be read.');
    }

    public function getContents(): string
    {
        throw new RuntimeException('An upload stream cannot be read.');
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }

    public function close(): void {}

    public function detach()
    {
        return null;
    }

    public function __toString(): string
    {
        return '';
    }
}
