<?php

namespace App\Enums;

/**
 * Which envelope a bid document belongs to.
 *
 * `Single` is the whole submission for a one-envelope tender; `Technical` and
 * `Financial` are the two halves of a two-envelope one, opened at different
 * times.
 */
enum EnvelopeType: string
{
    case Single = 'single';
    case Technical = 'technical';
    case Financial = 'financial';

    public function labelKey(): string
    {
        return 'bid.envelope.'.$this->value;
    }

    /** The envelopes a tender actually presents, given its mode. */
    public static function forTender(bool $isTwoEnvelope): array
    {
        return $isTwoEnvelope
            ? [self::Technical, self::Financial]
            : [self::Single];
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
