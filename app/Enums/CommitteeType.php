<?php

namespace App\Enums;

/**
 * What an evaluation committee is convened to judge.
 *
 * Deliberately NOT interchangeable with EnvelopeType, which labels a criterion.
 * The two overlap on 'technical' and 'financial' and diverge on 'combined' and
 * 'single', and the code used to compare them directly — so a Combined
 * committee matched `envelope = 'combined'`, a value nothing can write, and its
 * members were handed an empty scoring form. Use criterionEnvelopes() to cross
 * between the two.
 */
enum CommitteeType: string
{
    case Technical = 'technical';
    case Financial = 'financial';
    case Combined = 'combined';

    /**
     * Criterion envelopes a committee of this type is entitled to score.
     *
     * 'single' is included for the two split types because a criterion carrying
     * it belongs to no particular envelope; excluding it would hide the
     * criterion from every committee at once.
     *
     * @return array<int, string>
     */
    public function criterionEnvelopes(): array
    {
        return match ($this) {
            self::Technical => [EnvelopeType::Technical->value, EnvelopeType::Single->value],
            self::Financial => [EnvelopeType::Financial->value, EnvelopeType::Single->value],
            self::Combined => array_map(fn (EnvelopeType $e) => $e->value, EnvelopeType::cases()),
        };
    }

    public function labelKey(): string
    {
        return 'eval.'.$this->value;
    }
}
