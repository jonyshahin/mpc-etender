<?php

namespace App\Enums;

/**
 * Prequalification document types a vendor can hold on file.
 *
 * Single source of truth for the upload forms, their validation and the
 * `vendor_documents.document_type` cast. The cases below are the ones the
 * vendor and admin pickers offer; they previously drifted apart, so four of
 * the eight options the vendor was shown were rejected by validation.
 */
enum DocumentType: string
{
    case TradeLicense = 'trade_license';
    case TaxCertificate = 'tax_certificate';
    case Insurance = 'insurance';
    case FinancialStatement = 'financial_statement';
    case BankReference = 'bank_reference';
    case ExperienceCertificate = 'experience_certificate';
    case IsoCertificate = 'iso_certificate';
    case Other = 'other';

    /** Translation key for the human-facing label, shared by both pickers. */
    public function labelKey(): string
    {
        return 'vendor.doc_type_'.$this->value;
    }

    /** @return array<int, array{value: string, labelKey: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'labelKey' => $case->labelKey()],
            self::cases(),
        );
    }
}
