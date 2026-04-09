<?php

namespace App\Enums;

enum TenderType: string
{
    case Open = 'open';
    case Restricted = 'restricted';
    case DirectInvitation = 'direct_invitation';
    case Framework = 'framework';
}
