<?php

namespace App\Enums;

enum DocumentType: string
{
    case TradeLicense = 'trade_license';
    case Insurance = 'insurance';
    case FinancialStatement = 'financial_statement';
    case Reference = 'reference';
    case Certificate = 'certificate';
    case Other = 'other';
}
