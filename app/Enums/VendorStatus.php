<?php

namespace App\Enums;

enum VendorStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Qualified = 'qualified';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Blacklisted = 'blacklisted';
}
