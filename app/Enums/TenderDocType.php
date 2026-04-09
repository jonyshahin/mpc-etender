<?php

namespace App\Enums;

enum TenderDocType: string
{
    case Specification = 'specification';
    case Drawing = 'drawing';
    case ContractTerms = 'contract_terms';
    case BoqTemplate = 'boq_template';
    case SitePhoto = 'site_photo';
    case Other = 'other';
}
