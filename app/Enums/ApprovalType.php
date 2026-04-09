<?php

namespace App\Enums;

enum ApprovalType: string
{
    case Award = 'award';
    case Cancellation = 'cancellation';
    case Extension = 'extension';
    case BudgetOverride = 'budget_override';
}
