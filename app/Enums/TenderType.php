<?php

namespace App\Enums;

/**
 * How a tender is opened to the market.
 *
 * The vendor detail page rendered this by de-underscoring the raw value —
 * "direct invitation" — which is neither a label nor translatable. labelKey()
 * points at the entries the catalogue already carries.
 */
enum TenderType: string
{
    case Open = 'open';
    case Restricted = 'restricted';
    case DirectInvitation = 'direct_invitation';
    case Framework = 'framework';

    /** Translation key for the human-facing label. */
    public function labelKey(): string
    {
        return 'tender.type_'.$this->value;
    }
}
