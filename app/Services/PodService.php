<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\ProofOfDelivery;
use App\Models\Shipment;
use App\Models\TrackingLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PodService
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws Throwable
     */
    public function submitPod(
        Shipment $shipment,
        User $courier,
        array $data,
        UploadedFile $photoFile,
        UploadedFile $signatureFile
    ): ProofOfDelivery {
        if ($shipment->courier_id !== $courier->id) {
            throw new AuthorizationException('Anda tidak berwenang menyerahkan shipment milik kurir lain.');
        }

        if ($shipment->proofOfDelivery()->exists() || $shipment->status === ShipmentStatus::DELIVERED) {
            throw ValidationException::withMessages([
                'shipment' => ['Proof of Delivery untuk shipment ini sudah pernah dikirim sebelumnya.'],
            ]);
        }

        if (! $shipment->status->canTransitionTo(ShipmentStatus::DELIVERED)) {
            throw ValidationException::withMessages([
                'shipment' => [
                    "POD hanya dapat dibuat jika status shipment sudah 'Received at Destination'. Status saat ini: '{$shipment->status->value}'.",
                ],
            ]);
        }

        $photoPath = $photoFile->store('pod/photos', 'public');
        $signaturePath = $signatureFile->store('pod/signatures', 'public');

        try {
            return DB::transaction(function () use ($shipment, $courier, $data, $photoPath, $signaturePath) {
                $deliveredAt = now();

                $pod = ProofOfDelivery::create([
                    'shipment_id' => $shipment->id,
                    'courier_id' => $courier->id,
                    'recipient_name' => $data['recipient_name'],
                    'recipient_relation' => $data['recipient_relation'] ?? 'Yang Bersangkutan',
                    'photo_path' => $photoPath,
                    'signature_path' => $signaturePath,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'delivered_at' => $deliveredAt,
                ]);

                $shipment->update([
                    'status' => ShipmentStatus::DELIVERED,
                ]);

                TrackingLog::create([
                    'shipment_id' => $shipment->id,
                    'updated_by' => $courier->id,
                    'status' => ShipmentStatus::DELIVERED,
                    'description' => "Paket telah diterima oleh {$data['recipient_name']}.",
                    'location' => $shipment->destination_address,
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                ]);

                return $pod;
            });
        } catch (Throwable $e) {
            // Hapus file yang terlanjur diunggah jika transaksi database gagal
            Storage::disk('public')->delete([$photoPath, $signaturePath]);
            throw $e;
        }
    }
}