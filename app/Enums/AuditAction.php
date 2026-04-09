<?php

namespace App\Enums;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Viewed = 'viewed';
    case Downloaded = 'downloaded';
    case Opened = 'opened';
    case Sealed = 'sealed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Login = 'login';
    case Logout = 'logout';
}
