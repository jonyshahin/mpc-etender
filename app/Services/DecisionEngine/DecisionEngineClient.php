<?php

namespace App\Services\DecisionEngine;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The HTTP edge of the Decision Engine. Nothing else in eTender talks to it.
 *
 * Every method either returns the engine's decoded JSON or throws a
 * DecisionEngineException — never a bare Guzzle or Laravel HTTP exception, so
 * callers handle exactly two outcomes. The bearer token comes from config and
 * is never included in an exception message or a log line.
 *
 * Paths are the engine's real routes, not the contract's prose: the batch
 * result is GET /v1/decisions/batch/{id}.
 */
class DecisionEngineClient
{
    /**
     * The schema's latest version, or null when the key is not registered.
     *
     * @return array{key: string, version: int, definition: array}|null
     */
    public function latestSchema(string $key): ?array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('/v1/schemas/'.rawurlencode($key)));

        if ($response->status() === 404) {
            return null;
        }

        return $this->decode($response);
    }

    /**
     * Register a schema. The engine always creates a new version, so call this
     * only when no existing version matches.
     *
     * @return array{key: string, version: int, definition: array}
     */
    public function createSchema(array $definition): array
    {
        return $this->decode($this->send(fn (PendingRequest $http) => $http->post('/v1/schemas', $definition)));
    }

    /**
     * Create a profile. A key that already exists is `409 profile_exists`,
     * which the caller handles — it is the expected answer on a retry.
     *
     * @return array{key: string, status: string, locked_at: ?string, definition: array}
     */
    public function createProfile(array $body): array
    {
        return $this->decode($this->send(fn (PendingRequest $http) => $http->post('/v1/profiles', $body)));
    }

    /** @return array{key: string, status: string, locked_at: ?string, definition: array} */
    public function profile(string $key): array
    {
        return $this->decode($this->send(fn (PendingRequest $http) => $http->get('/v1/profiles/'.rawurlencode($key))));
    }

    /**
     * Lock a profile. Idempotent on the engine: locking a locked profile
     * returns it unchanged, with its original locked_at.
     *
     * @return array{key: string, status: string, locked_at: ?string, definition: array}
     */
    public function lockProfile(string $key): array
    {
        return $this->decode($this->send(fn (PendingRequest $http) => $http->post('/v1/profiles/'.rawurlencode($key).'/lock')));
    }

    /**
     * Submit a batch. Always async on the engine: the answer is 202 with an id.
     *
     * The Idempotency-Key is sent on every submission so an unchanged retry can
     * return the original batch. Note: as of 2026-10-10 the engine honours the
     * header on POST /v1/decisions only, not on the batch endpoint — see the
     * stage A notes.
     *
     * @return array{batch_id: string, status: string}
     */
    public function submitBatch(array $body, string $idempotencyKey): array
    {
        return $this->decode($this->send(
            fn (PendingRequest $http) => $http->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post('/v1/decisions/batch', $body),
        ));
    }

    /** @return array<string, mixed> the batch result */
    public function batch(string $batchId): array
    {
        return $this->decode($this->send(fn (PendingRequest $http) => $http->get('/v1/decisions/batch/'.rawurlencode($batchId))));
    }

    /**
     * Run one request, turning every way it can fail to reach a usable answer
     * into DecisionEngineException.
     *
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        $config = config('services.decision_engine');

        if (blank($config['token'] ?? null)) {
            // Configuration, not a network failure — but to the officer it is
            // the same thing: the engine cannot be used right now.
            throw DecisionEngineException::unavailable(__('engine.error.not_configured'));
        }

        $http = Http::baseUrl(rtrim((string) $config['url'], '/'))
            ->withToken($config['token'])
            ->acceptJson()
            ->asJson()
            ->connectTimeout($config['connect_timeout'])
            ->timeout($config['timeout']);

        try {
            $response = $call($http);
        } catch (ConnectionException) {
            // Refused, DNS, TLS or timed out. The request may or may not have
            // arrived — callers must treat this as "outcome unknown".
            throw DecisionEngineException::unavailable(__('engine.error.unreachable'));
        }

        // Rate limiting and the engine's own failures are not the officer's
        // doing and may clear on a retry, so they count as unavailable.
        if ($response->status() === 429 || $response->serverError()) {
            throw DecisionEngineException::unavailable(__('engine.error.engine_failed', ['status' => $response->status()]), $response->status());
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        if ($response->successful()) {
            $json = $response->json();

            if (! is_array($json)) {
                throw DecisionEngineException::unavailable(__('engine.error.unreadable_response'), $response->status());
            }

            return $json;
        }

        // The engine's standard error envelope: { error: { type, message, field } }.
        $error = $response->json('error');

        if ($response->status() === 401 || $response->status() === 403) {
            // A wrong or missing token is our configuration, not something the
            // officer can fix by retrying.
            throw DecisionEngineException::rejected($response->status(), $error['type'] ?? null, __('engine.error.not_authorised'));
        }

        throw DecisionEngineException::rejected(
            $response->status(),
            is_array($error) ? ($error['type'] ?? null) : null,
            is_array($error) && is_string($error['message'] ?? null)
                ? $error['message']
                : __('engine.error.rejected', ['status' => $response->status()]),
            is_array($error) ? ($error['field'] ?? null) : null,
        );
    }
}
