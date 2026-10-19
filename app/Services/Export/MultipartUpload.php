<?php

namespace App\Services\Export;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3ClientInterface;
use GuzzleHttp\Psr7\Utils;
use RuntimeException;
use Throwable;

/**
 * An S3 multipart upload fed as bytes arrive. Holds at most one part in
 * memory and nothing on disk.
 *
 * Sends exactly what the AWS SDK's own MultipartUploader sends —
 * CreateMultipartUpload, UploadPart (number, body, length), then
 * CompleteMultipartUpload with each part's number and ETag — so storage that
 * accepts the SDK's uploads (Cloudflare R2 behind Laravel Cloud, MinIO)
 * accepts these. The SDK's checksum middleware applies to both alike.
 *
 * Every part but the last is exactly PART_SIZE: S3 needs at least 5 MiB, and
 * R2 needs all but the last to be the same size. A file that never fills one
 * part is sent with a single PutObject instead.
 */
class MultipartUpload extends StorageUpload
{
    /** 8 MiB: above S3's 5 MiB minimum; 10,000 parts makes an 80 GB ceiling. */
    public const PART_SIZE = 8 * 1024 * 1024;

    private string $buffer = '';

    private int $bytes = 0;

    private ?string $uploadId = null;

    /** @var array<int, array{PartNumber: int, ETag: string}> */
    private array $parts = [];

    private bool $completed = false;

    public function __construct(
        private readonly S3ClientInterface $client,
        private readonly string $bucket,
        private readonly string $key,
        private readonly string $path,
        private readonly string $contentType = 'application/octet-stream',
        private readonly int $partSize = self::PART_SIZE,
    ) {}

    public function write(string $bytes): void
    {
        if ($this->completed) {
            throw new RuntimeException("{$this->path} is already complete.");
        }

        $this->buffer .= $bytes;
        $this->bytes += strlen($bytes);

        while (strlen($this->buffer) >= $this->partSize) {
            $this->sendPart(substr($this->buffer, 0, $this->partSize));
            $this->buffer = substr($this->buffer, $this->partSize);
        }
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

        if ($this->uploadId === null) {
            // Never filled a part: one request does it.
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $this->key,
                'Body' => $this->buffer,
                'ContentType' => $this->contentType,
                'ACL' => 'private',
            ]);
        } else {
            if ($this->buffer !== '') {
                $this->sendPart($this->buffer);
            }

            $this->client->completeMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $this->key,
                'UploadId' => $this->uploadId,
                'MultipartUpload' => ['Parts' => $this->parts],
            ]);
        }

        $this->buffer = '';
        $this->completed = true;

        return $this->path;
    }

    public function abort(): bool
    {
        $this->buffer = '';

        if ($this->completed || $this->uploadId === null) {
            return true;
        }

        try {
            $this->client->abortMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $this->key,
                'UploadId' => $this->uploadId,
            ]);
        } catch (S3Exception $e) {
            // Already gone: storage finished it (a completion whose reply was
            // lost) or expired it. Either way no unfinished upload remains.
            if (! self::isNoSuchUpload($e)) {
                return false;
            }
        } catch (Throwable) {
            // Left for the next export (or --clean) to abort; R2 also expires
            // abandoned uploads on its own after seven days.
            return false;
        }

        $this->uploadId = null;

        return true;
    }

    /** S3's answer for an upload it no longer has, finished or aborted. */
    public static function isNoSuchUpload(S3Exception $e): bool
    {
        return $e->getAwsErrorCode() === 'NoSuchUpload' || $e->getStatusCode() === 404;
    }

    public function path(): string
    {
        return $this->path;
    }

    private function sendPart(string $body): void
    {
        $this->uploadId ??= $this->client->createMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $this->key,
            'ContentType' => $this->contentType,
            'ACL' => 'private',
        ])['UploadId'];

        $number = count($this->parts) + 1;
        $result = $this->client->uploadPart([
            'Bucket' => $this->bucket,
            'Key' => $this->key,
            'UploadId' => $this->uploadId,
            'PartNumber' => $number,
            // A stream rather than the string: the SDK copies a string body
            // several times over (about 100 MB peak for an 8 MiB part).
            'Body' => Utils::streamFor($body),
            'ContentLength' => strlen($body),
        ]);

        $this->parts[] = ['PartNumber' => $number, 'ETag' => $result['ETag']];

        // The SDK leaves each request in reference cycles that only PHP's
        // cycle collector frees, and with few objects per call it seldom runs:
        // measured, every part sent stayed in memory to the end of the upload.
        unset($result, $body);
        gc_collect_cycles();
    }
}
