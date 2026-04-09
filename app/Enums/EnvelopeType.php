<?php

namespace App\Enums;

enum EnvelopeType: string
{
    case Single = 'single';
    case Technical = 'technical';
    case Financial = 'financial';
}
