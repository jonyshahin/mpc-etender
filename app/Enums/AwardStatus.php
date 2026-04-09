<?php

namespace App\Enums;

enum AwardStatus: string
{
    case Pending = 'pending';
    case Notified = 'notified';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
