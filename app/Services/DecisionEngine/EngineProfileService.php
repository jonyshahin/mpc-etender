<?php

namespace App\Services\DecisionEngine;

use App\Models\DecisionEngineSchema;
use App\Models\Tender;
use App\Models\TenderEngineProfile;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Turns a tender's scoring criteria into a locked profile in the engine.
 *
 * The lock is the integrity rule this whole integration rests on
 * (INTEGRATION-DESIGN §3): a tender must not accept bids against weights that
 * can still change, or someone could read the bids and tune the weights until
 * the preferred vendor wins. So lock() either returns a profile the engine has
 * confirmed locked AND whose stored definition matches, field for field, what
 * eTender sent — or it throws, and the tender stays a draft.
 *
 * Nothing here writes to eTender's database except the schema registration
 * record; recording the lock and opening the tender is the caller's single
 * transaction, which runs only after this returns.
 */
class EngineProfileService
{
    public const SCHEMA_KEY = 'etender_bid_evaluation';

    public function __construct(private DecisionEngineClient $engine) {}

    /**
     * Lock the tender's profile in the engine and return what was locked.
     *
     * Retry-safe by construction. The profile key carries a hash of the
     * definition, so an unchanged retry targets the same key and meets
     * `409 profile_exists` — expected, not an error — and the engine's lock is
     * idempotent. Edited weights change the hash and make a new profile, so an
     * abandoned attempt's profile is never adopted with weights eTender no
     * longer holds.
     *
     * @return array{profile_key: string, schema_key: string, schema_version: int, definition: array, locked_at: string}
     *
     * @throws DecisionEngineException when the engine cannot be reached or refuses
     * @throws RuntimeException when the engine reports a profile eTender did not send
     */
    public function lock(Tender $tender, TenderEngineProfile $profile): array
    {
        $schemaVersion = $this->schemaVersion();
        $definition = $this->definition($tender, $profile, $schemaVersion);
        $key = $this->profileKey($tender, $definition);
        $definition = ['key' => $key] + $definition;

        try {
            $this->engine->createProfile($definition + ['status' => 'draft']);
        } catch (DecisionEngineException $e) {
            // The same key with the same content: an earlier attempt created it
            // and then failed to lock or to record the lock. Carry on and lock.
            if (! $e->isType('profile_exists')) {
                throw $e;
            }
        }

        $locked = $this->engine->lockProfile($key);

        if (($locked['status'] ?? null) !== 'locked' || blank($locked['locked_at'] ?? null)) {
            throw new RuntimeException(__('engine.publish.lock_not_confirmed'));
        }

        // The engine stores a profile exactly as sent (verified against the
        // engine's own code). Anything else means this key was created by
        // something other than this request — refuse to open against it.
        if (self::canonical($locked['definition'] ?? []) !== self::canonical($definition)) {
            throw new RuntimeException(__('engine.publish.lock_mismatch', ['key' => $key]));
        }

        return [
            'profile_key' => $key,
            'schema_key' => self::SCHEMA_KEY,
            'schema_version' => $schemaVersion,
            'definition' => $definition,
            // The engine's clock, not ours: it is the authoritative lock time,
            // and the engine never accepts one from a caller.
            'locked_at' => $locked['locked_at'],
        ];
    }

    /**
     * The engine version that holds eTender's current schema file, registering
     * it the first time this content is seen.
     *
     * @throws DecisionEngineException
     */
    public function schemaVersion(): int
    {
        $definition = $this->schemaDefinition();
        $hash = hash('sha256', self::canonical($definition));

        $known = fn () => DecisionEngineSchema::query()
            ->where('key', self::SCHEMA_KEY)
            ->where('content_hash', $hash)
            ->value('version');

        if ($version = $known()) {
            return (int) $version;
        }

        // Two publishes meeting an unregistered schema would otherwise each
        // POST it and mint two identical engine versions.
        return Cache::lock("decision-engine:schema:{$hash}", 30)->block(20, function () use ($known, $definition, $hash) {
            if ($version = $known()) {
                return (int) $version;
            }

            $created = $this->engine->createSchema($definition);

            DecisionEngineSchema::create([
                'key' => self::SCHEMA_KEY,
                'content_hash' => $hash,
                'version' => (int) $created['version'],
            ]);

            return (int) $created['version'];
        });
    }

    /**
     * The profile body for one tender, without its key.
     *
     * Every active judgement criterion is caller-scored, so `always_review_if`
     * stays empty — listing one would mean "have a person review what a person
     * scored", which the engine rejects. Thin margins are still flagged.
     */
    public function definition(Tender $tender, TenderEngineProfile $profile, int $schemaVersion): array
    {
        $weights = $profile->weights();

        $scored = fn (string $key, array $extra = []) => [
            'key' => $key,
            'active' => $weights[$key] > 0,
        ] + ($weights[$key] > 0 ? ['weight' => $weights[$key]] : []) + $extra;

        return [
            'schema_key' => self::SCHEMA_KEY,
            'schema_version' => $schemaVersion,
            'label' => $tender->title_en ?: $tender->reference_number,
            'context' => [
                'tender_number' => $tender->reference_number,
                'contract_type' => $profile->contract_type->value === 'other'
                    ? (string) $profile->contract_type_label
                    : $profile->contract_type->value,
            ],
            'criteria' => [
                ['key' => 'submission_deadline', 'active' => true],
                ['key' => 'bank_guarantee', 'active' => $profile->gate_bank_guarantee],
                ['key' => 'mandatory_documents', 'active' => $profile->gate_mandatory_documents],
                $scored('price'),
                $scored('technical'),
                $scored('timeline', ['formula' => ['type' => $profile->timeline_formula->value, 'direction' => 'lower_is_better']]),
                $scored('references'),
                $scored('payment_flexibility'),
            ],
            'review_policy' => [
                'confidence_below' => 0.7,
                'any_criterion_below' => 40,
                'always_review_if' => [],
                'margin_below' => (float) $profile->margin_below,
            ],
        ];
    }

    /**
     * Tender reference plus a hash of the definition: stable across retries of
     * the same content, different the moment the content changes.
     */
    public function profileKey(Tender $tender, array $definitionWithoutKey): string
    {
        return $tender->reference_number.'-'.substr(hash('sha256', self::canonical($definitionWithoutKey)), 0, 12);
    }

    /** eTender's schema file, minus `_` annotations the engine strips anyway. */
    public function schemaDefinition(): array
    {
        $raw = json_decode(
            file_get_contents(resource_path('decision-engine/etender_bid_evaluation.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return self::withoutAnnotations($raw);
    }

    /**
     * Order-independent, number-normalised JSON for comparing definitions.
     *
     * Keys are sorted at every level so two equal objects compare equal
     * whatever order they were built in. Numbers are cast to float because the
     * engine stores 2.0 as 2; lists keep their order, which is meaningful.
     */
    public static function canonical(array $value): string
    {
        $normalise = function ($v) use (&$normalise) {
            if (is_array($v)) {
                if (! array_is_list($v)) {
                    ksort($v);
                }

                return array_map($normalise, $v);
            }

            return is_int($v) || is_float($v) ? (float) $v : $v;
        };

        return json_encode($normalise($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function withoutAnnotations(array $value): array
    {
        $out = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && str_starts_with($k, '_')) {
                continue;
            }
            $out[$k] = is_array($v) ? self::withoutAnnotations($v) : $v;
        }

        return $out;
    }
}
