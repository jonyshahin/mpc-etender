<?php

namespace Tests\Support;

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Closure;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Just enough S3 for tests, held in memory and plugged into the AWS SDK as its
 * HTTP handler — so Laravel's real s3 disk, Flysystem's S3 adapter and the SDK
 * client all run, offline.
 *
 * Enforces the multipart rules the code must respect in production: every
 * part but the last at least 5 MiB (S3), and all but the last the same size
 * (Cloudflare R2, behind Laravel Cloud's object storage).
 *
 * Every command sent is recorded; $failures can make one fail.
 */
class InMemoryS3
{
    public const MIN_PART = 5 * 1024 * 1024;

    /** @var array<string, string> key => contents */
    public array $objects = [];

    /** @var array<string, array{key: string, parts: array<int, string>}> */
    public array $uploads = [];

    /** @var array<int, array{name: string, key: ?string, size: ?int}> */
    public array $sent = [];

    /** @var array<string, Closure(CommandInterface): ?Throwable> command name => failure to raise */
    public array $failures = [];

    private int $nextUploadId = 1;

    /** Laravel's s3 disk over this store, as the app configures it. */
    public function disk(string $bucket = 'test-bucket'): FilesystemAdapter
    {
        return Storage::build([
            'driver' => 's3',
            'key' => 'test',
            'secret' => 'test',
            'region' => 'us-east-1',
            'bucket' => $bucket,
            'endpoint' => 'http://s3.test',
            'use_path_style_endpoint' => true,
            'throw' => false,
            'report' => false,
            'handler' => $this,
        ]);
    }

    /** @return array<int, string> the names of the commands sent, in order */
    public function sentNames(): array
    {
        return array_column($this->sent, 'name');
    }

    public function __invoke(CommandInterface $command, RequestInterface $request): PromiseInterface
    {
        $name = $command->getName();
        $body = isset($command['Body']) ? (string) Utils::streamFor($command['Body']) : null;
        $this->sent[] = ['name' => $name, 'key' => $command['Key'] ?? null, 'size' => $body === null ? null : strlen($body)];

        try {
            if (isset($this->failures[$name]) && ($failure = ($this->failures[$name])($command)) !== null) {
                throw $failure;
            }

            return Create::promiseFor(new Result(match ($name) {
                'PutObject' => $this->put($command['Key'], $body),
                'GetObject' => ['Body' => Utils::streamFor($this->object($command)), 'ContentLength' => strlen($this->object($command))],
                'HeadObject' => ['ContentLength' => strlen($this->object($command)), 'ContentType' => 'application/octet-stream'],
                'DeleteObject' => $this->delete($command['Key']),
                'ListObjectsV2' => $this->list((string) ($command['Prefix'] ?? ''), $command['Delimiter'] ?? null),
                'CreateMultipartUpload' => $this->create($command['Key']),
                'UploadPart' => $this->part($command, $body),
                'CompleteMultipartUpload' => $this->complete($command),
                'AbortMultipartUpload' => $this->abort($command),
                'ListMultipartUploads' => $this->listUploads((string) ($command['Prefix'] ?? '')),
                default => throw $this->error($command, 'NotImplemented', 501, "{$name} is not emulated"),
            }));
        } catch (Throwable $e) {
            return Create::rejectionFor($e);
        }
    }

    public function error(CommandInterface $command, string $code, int $status, string $message = ''): S3Exception
    {
        return new S3Exception($message ?: $code, $command, ['code' => $code, 'response' => new Response($status)]);
    }

    private function object(CommandInterface $command): string
    {
        return $this->objects[$command['Key']] ?? throw $this->error($command, 'NoSuchKey', 404);
    }

    private function put(string $key, ?string $body): array
    {
        $this->objects[$key] = (string) $body;

        return ['ETag' => '"'.md5((string) $body).'"'];
    }

    private function delete(string $key): array
    {
        unset($this->objects[$key]);

        return [];
    }

    private function list(string $prefix, ?string $delimiter): array
    {
        $contents = [];
        $prefixes = [];

        foreach ($this->objects as $key => $bytes) {
            if (! str_starts_with($key, $prefix)) {
                continue;
            }

            $rest = substr($key, strlen($prefix));

            if ($delimiter !== null && str_contains($rest, $delimiter)) {
                $prefixes[$prefix.strstr($rest, $delimiter, true).$delimiter] = true;
            } else {
                $contents[] = ['Key' => $key, 'Size' => strlen($bytes)];
            }
        }

        // Real S3 leaves both out when nothing matches, and Flysystem's
        // directoryExists() relies on that: their mere presence reads as "yes".
        return array_filter([
            'Contents' => $contents,
            'CommonPrefixes' => array_map(fn ($p) => ['Prefix' => $p], array_keys($prefixes)),
        ]) + ['IsTruncated' => false];
    }

    private function create(string $key): array
    {
        $id = 'upload-'.$this->nextUploadId++;
        $this->uploads[$id] = ['key' => $key, 'parts' => []];

        return ['UploadId' => $id, 'Key' => $key];
    }

    private function part(CommandInterface $command, ?string $body): array
    {
        $upload = &$this->upload($command);
        $upload['parts'][(int) $command['PartNumber']] = (string) $body;

        return ['ETag' => '"'.md5((string) $body).'"'];
    }

    private function complete(CommandInterface $command): array
    {
        $upload = $this->upload($command);
        $listed = $command['MultipartUpload']['Parts'] ?? [];

        if ($listed === []) {
            throw $this->error($command, 'MalformedXML', 400, 'No parts listed');
        }

        $sizes = [];
        $data = '';

        foreach ($listed as $i => $part) {
            $body = $upload['parts'][$part['PartNumber']] ?? throw $this->error($command, 'InvalidPart', 400);

            if ($part['ETag'] !== '"'.md5($body).'"') {
                throw $this->error($command, 'InvalidPart', 400, "ETag mismatch on part {$part['PartNumber']}");
            }

            if ($i > 0 && $part['PartNumber'] <= $listed[$i - 1]['PartNumber']) {
                throw $this->error($command, 'InvalidPartOrder', 400);
            }

            $sizes[] = strlen($body);
            $data .= $body;
        }

        $allButLast = array_slice($sizes, 0, -1);

        if ($allButLast !== [] && min($allButLast) < self::MIN_PART) {
            throw $this->error($command, 'EntityTooSmall', 400, 'A part other than the last is under 5 MiB');
        }

        if (count(array_unique($allButLast)) > 1) {
            throw $this->error($command, 'InvalidPart', 400, 'R2: all parts but the last must be the same size');
        }

        $this->objects[$upload['key']] = $data;
        unset($this->uploads[$command['UploadId']]);

        return ['Key' => $upload['key'], 'ETag' => '"'.md5($data).'-'.count($sizes).'"'];
    }

    private function abort(CommandInterface $command): array
    {
        $this->upload($command);
        unset($this->uploads[$command['UploadId']]);

        return [];
    }

    private function listUploads(string $prefix): array
    {
        $uploads = [];

        foreach ($this->uploads as $id => $upload) {
            if (str_starts_with($upload['key'], $prefix)) {
                $uploads[] = ['Key' => $upload['key'], 'UploadId' => $id];
            }
        }

        return ['Uploads' => $uploads, 'IsTruncated' => false];
    }

    /** @return array{key: string, parts: array<int, string>} */
    private function &upload(CommandInterface $command): array
    {
        if (! isset($this->uploads[$command['UploadId']])) {
            throw $this->error($command, 'NoSuchUpload', 404);
        }

        return $this->uploads[$command['UploadId']];
    }
}
