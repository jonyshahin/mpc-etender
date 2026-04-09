<?php

namespace App\Enums;

enum BidDocType: string
{
    case TechnicalProposal = 'technical_proposal';
    case MethodStatement = 'method_statement';
    case Certificate = 'certificate';
    case FinancialSchedule = 'financial_schedule';
    case Other = 'other';
}
