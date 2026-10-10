<?php

namespace Tests\Support;

use Closure;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * A stand-in for the Decision Engine, installed for every test.
 *
 * Answers in the engine's real shapes — captured by running eTender's schema,
 * profile and batches through the engine's own code — so the client and the
 * screens are tested against what the engine actually returns, not against the
 * contract's prose. Stray requests are refused: no test can reach a real
 * engine by accident.
 *
 * Healthy by default. A test changes behaviour with the static helpers:
 * unreachable(), failOn(), keepBatchesProcessing(), batchResult().
 */
final class FakeDecisionEngine
{
    public const URL = 'https://engine.test';

    /** @var array<string, array{definition: array, status: string, locked_at: ?string}> */
    public static array $profiles = [];

    /** @var array<int, int> schema version per POST, in order */
    public static array $schemaVersions = [];

    /** @var array<string, array> batch id => the request body it was created from */
    public static array $batches = [];

    /** @var array<int, array{method: string, path: string, body: array, headers: array}> */
    public static array $requests = [];

    /** When true, every request fails as if the engine could not be reached. */
    public static bool $unreachable = false;

    /** @var array<string, Closure(Request): mixed> "METHOD path-regex" => responder override */
    public static array $overrides = [];

    public static bool $batchesStayProcessing = false;

    /** @var (Closure(array, string): array)|null */
    public static ?Closure $batchResult = null;

    public static function install(): void
    {
        self::$profiles = [];
        self::$schemaVersions = [];
        self::$batches = [];
        self::$requests = [];
        self::$unreachable = false;
        self::$overrides = [];
        self::$batchesStayProcessing = false;
        self::$batchResult = null;

        config([
            'services.decision_engine.url' => self::URL,
            'services.decision_engine.token' => 'test-engine-token',
        ]);

        Http::preventStrayRequests();
        Http::fake([self::URL.'/*' => fn (Request $request) => self::handle($request)]);
    }

    public static function unreachable(bool $unreachable = true): void
    {
        self::$unreachable = $unreachable;
    }

    /**
     * Make one kind of request fail. $pattern is "METHOD regex" against the
     * path, e.g. "POST #/lock$#". The responder may return a response or call
     * Http::failedConnection().
     */
    public static function failOn(string $pattern, Closure $responder): void
    {
        self::$overrides[$pattern] = $responder;
    }

    public static function keepBatchesProcessing(bool $stay = true): void
    {
        self::$batchesStayProcessing = $stay;
    }

    /** @param Closure(array $body, string $batchId): array $result */
    public static function batchResult(Closure $result): void
    {
        self::$batchResult = $result;
    }

    /** @return array<int, array> requests matching a method and path fragment */
    public static function requestsTo(string $method, string $pathContains): array
    {
        return array_values(array_filter(
            self::$requests,
            fn ($r) => $r['method'] === $method && str_contains($r['path'], $pathContains),
        ));
    }

    public static function error(int $status, string $type, string $message): mixed
    {
        return Http::response(['error' => ['type' => $type, 'message' => $message, 'request_id' => 'req_fake']], $status);
    }

    private static function handle(Request $request): mixed
    {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $method = $request->method();
        $body = $request->data() ?: [];

        self::$requests[] = [
            'method' => $method,
            'path' => $path,
            'body' => $body,
            'headers' => $request->headers(),
        ];

        if (self::$unreachable) {
            return (Http::failedConnection())($request);
        }

        foreach (self::$overrides as $pattern => $responder) {
            [$m, $regex] = explode(' ', $pattern, 2);
            if ($m === $method && preg_match($regex, $path)) {
                $out = $responder($request);

                return $out instanceof Closure ? $out($request) : $out;
            }
        }

        $authorization = $request->header('Authorization')[0] ?? '';

        if (! str_starts_with($authorization, 'Bearer ')) {
            return self::error(401, 'invalid_api_key', 'Missing bearer token.');
        }

        return match (true) {
            $method === 'POST' && $path === '/v1/schemas' => self::createSchema($body),
            $method === 'GET' && preg_match('#^/v1/schemas/([^/]+)$#', $path) === 1 => self::error(404, 'schema_not_found', 'Not registered.'),
            $method === 'POST' && $path === '/v1/profiles' => self::createProfile($body),
            $method === 'GET' && preg_match('#^/v1/profiles/([^/]+)$#', $path, $m) === 1 => self::showProfile(urldecode($m[1])),
            $method === 'POST' && preg_match('#^/v1/profiles/([^/]+)/lock$#', $path, $m) === 1 => self::lockProfile(urldecode($m[1])),
            $method === 'POST' && $path === '/v1/decisions/batch' => self::createBatch($body),
            $method === 'GET' && preg_match('#^/v1/decisions/batch/([^/]+)$#', $path, $m) === 1 => self::showBatch(urldecode($m[1])),
            default => self::error(404, 'not_found', "No fake route for {$method} {$path}"),
        };
    }

    private static function createSchema(array $body): mixed
    {
        $version = count(self::$schemaVersions) + 1;
        self::$schemaVersions[] = $version;

        return Http::response(['key' => $body['key'], 'version' => $version, 'definition' => $body, 'created_at' => now()->toIso8601ZuluString()], 201);
    }

    private static function createProfile(array $body): mixed
    {
        $key = $body['key'];

        if (isset(self::$profiles[$key])) {
            return self::error(409, 'profile_exists', "Profile '{$key}' already exists. A changed profile is a new profile with a new key.");
        }

        $status = $body['status'] ?? 'draft';
        unset($body['status']);
        self::$profiles[$key] = [
            'definition' => $body,
            'status' => $status,
            'locked_at' => $status === 'locked' ? now()->toIso8601ZuluString() : null,
        ];

        return Http::response(self::profileJson($key), 201);
    }

    private static function showProfile(string $key): mixed
    {
        return isset(self::$profiles[$key])
            ? Http::response(self::profileJson($key))
            : self::error(404, 'profile_not_found', "Profile '{$key}' does not exist.");
    }

    private static function lockProfile(string $key): mixed
    {
        if (! isset(self::$profiles[$key])) {
            return self::error(404, 'profile_not_found', "Profile '{$key}' does not exist.");
        }

        if (self::$profiles[$key]['status'] !== 'locked') {
            self::$profiles[$key]['status'] = 'locked';
            self::$profiles[$key]['locked_at'] = now()->toIso8601ZuluString();
        }

        return Http::response(self::profileJson($key));
    }

    private static function profileJson(string $key): array
    {
        $p = self::$profiles[$key];

        return [
            'key' => $key,
            'schema' => ['key' => $p['definition']['schema_key'] ?? null, 'version' => $p['definition']['schema_version'] ?? null],
            'status' => $p['status'],
            'locked_at' => $p['locked_at'],
            'definition' => $p['definition'],
        ];
    }

    private static function createBatch(array $body): mixed
    {
        $profile = self::$profiles[$body['profile_key'] ?? ''] ?? null;

        if ($profile === null) {
            return self::error(404, 'profile_not_found', 'Profile does not exist.');
        }

        if ($profile['status'] !== 'locked') {
            return self::error(409, 'profile_not_locked', 'Profile must be locked before it is used to evaluate anything.');
        }

        $id = 'bat_'.Str::lower((string) Str::ulid());
        self::$batches[$id] = $body;

        return Http::response(['batch_id' => $id, 'status' => 'queued'], 202);
    }

    private static function showBatch(string $id): mixed
    {
        if (! isset(self::$batches[$id])) {
            return self::error(404, 'batch_not_found', "Batch '{$id}' does not exist.");
        }

        $body = self::$batches[$id];

        if (self::$batchesStayProcessing) {
            return Http::response([
                'batch_id' => $id,
                'status' => 'processing',
                'schema' => ['key' => $body['schema_key'], 'version' => $body['schema_version']],
                'profile' => ['key' => $body['profile_key'], 'locked_at' => null],
                'supersedes' => $body['supersedes'] ?? null,
                'superseded_by' => [],
                'decisions' => [],
                'created_at' => now()->toIso8601ZuluString(),
                'completed_at' => null,
            ]);
        }

        return Http::response(self::$batchResult
            ? (self::$batchResult)($body, $id)
            : EngineResults::completed($body, $id));
    }
}
