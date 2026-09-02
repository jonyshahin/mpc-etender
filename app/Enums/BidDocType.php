<?php

namespace App\Enums;

/**
 * What a bid attachment is.
 *
 * The vendor bid page maintained three separate hardcoded copies of these
 * values — one per envelope picker — which validation had no way to stay in
 * step with. The envelope split now comes from here too, so a new case reaches
 * the pickers and BidDocumentRequest together.
 */
enum BidDocType: string
{
    case TechnicalProposal = 'technical_proposal';
    case MethodStatement = 'method_statement';
    case Certificate = 'certificate';
    case FinancialSchedule = 'financial_schedule';
    case Other = 'other';

    /** Translation key for the human-facing label, shared by every picker. */
    public function labelKey(): string
    {
        return 'bid.doc_type.'.$this->value;
    }

    /**
     * Which envelopes may carry this type.
     *
     * `Other` belongs to both. A single-envelope tender accepts every case —
     * see {@see optionsFor()}.
     */
    public function envelopes(): array
    {
        return match ($this) {
            self::FinancialSchedule => [EnvelopeType::Financial],
            self::Other => [EnvelopeType::Technical, EnvelopeType::Financial],
            default => [EnvelopeType::Technical],
        };
    }

    /** @return array<int, array{value: string, labelKey: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'labelKey' => $case->labelKey()],
            self::cases(),
        );
    }

    /**
     * The types a given envelope accepts.
     *
     * `Single` gets the full list so the vendor can pick the most accurate
     * label; the two-envelope splits get only what belongs on their side.
     *
     * @return array<int, array{value: string, labelKey: string}>
     */
    public static function optionsFor(EnvelopeType $envelope): array
    {
        if ($envelope === EnvelopeType::Single) {
            return self::options();
        }

        return array_values(array_map(
            fn (self $case) => ['value' => $case->value, 'labelKey' => $case->labelKey()],
            array_filter(self::cases(), fn (self $case) => in_array($envelope, $case->envelopes(), true)),
        ));
    }
}
