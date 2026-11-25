<?php

namespace App\Services;

use App\Enums\ManifestStatus;
use App\Enums\ShipmentStatus;
use App\Models\Manifest;
use App\Models\ManifestDetail;
use App\Models\Shipment;
use App\Models\TrackingLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManifestService
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function scanAwb(Manifest $manifest, User $courier, string $rawAwbNumber): ManifestDetail
    {
        if ($manifest->courier_id !== $courier->id) {
            throw new AuthorizationException('Anda tidak berwenang mengakses manifest milik kurir lain.');
        }

        if ($manifest->status === ManifestStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'manifest' => ['Manifest ini sudah berstatus selesai (completed) dan tidak dapat dipindai lagi.'],
            ]);
        }

        $awbNumber = strtoupper(trim($rawAwbNumber));
        $shipment = Shipment::where('awb_number', $awbNumber)->first();

        if (! $shipment) {
            throw ValidationException::withMessages([
                'awb_number' => ["Nomor AWB '{$awbNumber}' tidak ditemukan di dalam sistem."],
            ]);
        }

        $detail = ManifestDetail::where('manifest_id', $manifest->id)
            ->where('shipment_id', $shipment->id)
            ->first();

        if (! $detail) {
            throw ValidationException::withMessages([
                'awb_number' => ["Paket dengan AWB '{$awbNumber}' tidak terdaftar pada manifest {$manifest->manifest_number}."],
            ]);
        }

        if ($detail->scanned_at !== null) {
            throw ValidationException::withMessages([
                'awb_number' => ["AWB '{$awbNumber}' sudah pernah dipindai pada manifest ini."],
            ]);
        }

        return DB::transaction(function () use ($detail, $shipment, $manifest, $courier) {
            $detail->update([
                'scanned_at' => now(),
            ]);

            // Jika paket masih berstatus Picked Up, otomatis naikkan menjadi Manifested
            if ($shipment->status === ShipmentStatus::PICKED_UP) {
                $shipment->update([
                    'status' => ShipmentStatus::MANIFESTED,
                ]);

                TrackingLog::create([
                    'shipment_id' => $shipment->id,
                    'updated_by' => $courier->id,
                    'status' => ShipmentStatus::MANIFESTED,
                    'description' => "Paket dipindai dan terverifikasi dalam manifest {$manifest->manifest_number}.",
                    'location' => $manifest->origin_hub,
                ]);
            }

            return $detail->load('shipment');
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateStatus(Manifest $manifest, User $courier, ManifestStatus $nextStatus): Manifest
    {
        if ($manifest->courier_id !== $courier->id) {
            throw new AuthorizationException('Anda tidak berwenang memperbarui manifest milik kurir lain.');
        }

        if (! $manifest->status->canTransitionTo($nextStatus)) {
            $expected = $manifest->status->nextAllowedStatus()?->value ?? 'tidak ada (manifest sudah selesai)';

            throw ValidationException::withMessages([
                'status' => [
                    "Perpindahan status manifest tidak valid dari '{$manifest->status->value}' ke '{$nextStatus->value}'. Status berikutnya yang diizinkan adalah: {$expected}.",
                ],
            ]);
        }

        if ($nextStatus === ManifestStatus::COMPLETED) {
            $unscannedCount = $manifest->details()->whereNull('scanned_at')->count();
            if ($unscannedCount > 0) {
                throw ValidationException::withMessages([
                    'status' => [
                        "Masih terdapat {$unscannedCount} paket yang belum dipindai pada manifest ini. Pindai seluruh AWB sebelum menyelesaikan manifest.",
                    ],
                ]);
            }
        }

        $manifest->update([
            'status' => $nextStatus,
        ]);

        return $manifest->fresh(['details.shipment']);
    }
}