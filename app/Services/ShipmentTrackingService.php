<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\TrackingLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentTrackingService
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function addTrackingLog(
        Shipment $shipment,
        User $actor,
        ShipmentStatus $nextStatus,
        string $description,
        ?string $location = null,
        ?float $latitude = null,
        ?float $longitude = null
    ): TrackingLog {
        if ($actor->isCourier() && $shipment->courier_id !== $actor->id) {
            throw new AuthorizationException('Anda tidak berwenang memperbarui shipment milik kurir lain.');
        }

        if ($nextStatus === ShipmentStatus::DELIVERED) {
            throw ValidationException::withMessages([
                'status' => ['Status Delivered hanya dapat diproses melalui fitur Proof of Delivery (POD).'],
            ]);
        }

        if (! $shipment->status->canTransitionTo($nextStatus)) {
            $expected = $shipment->status->nextAllowedStatus()?->value ?? 'tidak ada (status sudah final)';

            throw ValidationException::withMessages([
                'status' => [
                    "Perpindahan status tidak valid dari '{$shipment->status->value}' ke '{$nextStatus->value}'. Status berikutnya yang diizinkan adalah: {$expected}.",
                ],
            ]);
        }

        return DB::transaction(function () use (
            $shipment,
            $actor,
            $nextStatus,
            $description,
            $location,
            $latitude,
            $longitude
        ) {
            $shipment->update([
                'status' => $nextStatus,
            ]);

            return TrackingLog::create([
                'shipment_id' => $shipment->id,
                'updated_by' => $actor->id,
                'status' => $nextStatus,
                'description' => $description,
                'location' => $location,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
        });
    }
}