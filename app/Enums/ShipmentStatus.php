<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case AWB_CREATED = 'AWB Created';
    case PICKED_UP = 'Picked Up';
    case MANIFESTED = 'Manifested';
    case RECEIVED_AT_DESTINATION = 'Received at Destination';
    case DELIVERED = 'Delivered';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Status yang boleh diperbarui langsung melalui endpoint tracking.
     * Status Delivered hanya boleh dihasilkan melalui proses submit POD.
     */
    public static function trackingUpdatableValues(): array
    {
        return [
            self::PICKED_UP->value,
            self::MANIFESTED->value,
            self::RECEIVED_AT_DESTINATION->value,
        ];
    }

    public function nextAllowedStatus(): ?self
    {
        return match ($this) {
            self::AWB_CREATED => self::PICKED_UP,
            self::PICKED_UP => self::MANIFESTED,
            self::MANIFESTED => self::RECEIVED_AT_DESTINATION,
            self::RECEIVED_AT_DESTINATION => self::DELIVERED,
            self::DELIVERED => null,
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return $this->nextAllowedStatus() === $next;
    }
}