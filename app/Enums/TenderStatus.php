<?php

namespace App\Enums;

enum TenderStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case SubmissionClosed = 'submission_closed';
    case UnderEvaluation = 'under_evaluation';
    case Awarded = 'awarded';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
