<?php

namespace App\Enums;

/**
 * What a tender attachment is.
 *
 * The vendor detail page built its label key by concatenating this value onto
 * a prefix, which is the same drift risk as a literal list: a renamed case
 * would have silently rendered the raw key. labelKey() puts the mapping here.
 */
enum TenderDocType: string
{
    case Specification = 'specification';
    case Drawing = 'drawing';
    case ContractTerms = 'contract_terms';
    case BoqTemplate = 'boq_template';
    case SitePhoto = 'site_photo';
    case Other = 'other';

    /** Translation key for the human-facing label. */
    public function labelKey(): string
    {
        return 'tender.doc_'.$this->value;
    }
}
