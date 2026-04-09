<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Whatsapp = 'whatsapp';
    case Sms = 'sms';
    case Email = 'email';
    case InApp = 'in_app';
    case Broadcast = 'broadcast';
}
