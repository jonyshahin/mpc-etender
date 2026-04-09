<?php

namespace App\Enums;

enum CommitteeRole: string
{
    case Chair = 'chair';
    case Member = 'member';
    case Secretary = 'secretary';
}
