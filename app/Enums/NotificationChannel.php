<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Database = 'database';
    case Email    = 'email';
    case Sms      = 'sms';
    case Push     = 'push';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
