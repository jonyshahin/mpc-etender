<?php

namespace App\Enums;

enum NotificationType: string
{
    case TenderPublished = 'tender_published';
    case DeadlineReminder = 'deadline_reminder';
    case AddendumIssued = 'addendum_issued';
    case BidReceived = 'bid_received';
    case BidOpened = 'bid_opened';
    case EvaluationComplete = 'evaluation_complete';
    case AwardNotification = 'award_notification';
    case ApprovalRequired = 'approval_required';
    case DocumentExpiry = 'document_expiry';
}
