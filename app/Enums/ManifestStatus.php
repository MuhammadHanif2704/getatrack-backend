<?php

namespace App\Enums;

enum ManifestStatus: string
{
    case OPEN = 'open';
    case IN_TRANSIT = 'in_transit';
    case COMPLETED = 'completed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function nextAllowedStatus(): ?self
    {
        return match ($this) {
            self::OPEN => self::IN_TRANSIT,
            self::IN_TRANSIT => self::COMPLETED,
            self::COMPLETED => null,
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return $this->nextAllowedStatus() === $next;
    }
}