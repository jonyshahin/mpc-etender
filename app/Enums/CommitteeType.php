<?php

namespace App\Enums;

enum CommitteeType: string
{
    case Technical = 'technical';
    case Financial = 'financial';
    case Combined = 'combined';
}
