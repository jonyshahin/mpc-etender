<?php

namespace App\Enums;

enum BidStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';
    case Opened = 'opened';
    case UnderEvaluation = 'under_evaluation';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Disqualified = 'disqualified';
}
