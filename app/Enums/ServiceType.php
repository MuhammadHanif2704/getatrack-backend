<?php

namespace App\Enums;

enum ServiceType: string
{
    case REGULAR = 'regular';
    case EXPRESS = 'express';
    case SAME_DAY = 'same_day';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular',
            self::EXPRESS => 'Express',
            self::SAME_DAY => 'Same Day',
        };
    }
}