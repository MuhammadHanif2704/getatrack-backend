<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case COURIER = 'courier';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}