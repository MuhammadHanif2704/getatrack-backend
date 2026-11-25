<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'awb_number' => $this->awb_number,
            'courier_id' => $this->courier_id,
            'sender_name' => $this->sender_name,
            'sender_phone' => $this->sender_phone,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'origin_address' => $this->origin_address,
            'destination_address' => $this->destination_address,
            'service_type' => $this->service_type->value,
            'item_description' => $this->item_description,
            'shipment_type' => $this->shipment_type ?? 'Paket Reguler',
            'service_label' => $this->service_type->label(),
            'weight_kg' => (float) $this->weight_kg,
            'status' => $this->status->value,
            'next_allowed_status' => $this->status->nextAllowedStatus()?->value,
            'courier' => $this->whenLoaded('courier', fn () => [
                'id' => $this->courier->id,
                'name' => $this->courier->name,
                'courier_code' => $this->courier->courier_code,
            ]),
            'manifests' => $this->whenLoaded('manifests', fn () => $this->manifests->map(fn ($manifest) => [
                'id' => $manifest->id,
                'manifest_number' => $manifest->manifest_number,
                'manifest_date' => $manifest->manifest_date?->format('Y-m-d'),
                'status' => $manifest->status->value,
                'scanned_at' => $manifest->pivot?->scanned_at,
            ])),
            'tracking_logs' => TrackingLogResource::collection($this->whenLoaded('trackingLogs')),
            'proof_of_delivery' => new PodResource($this->whenLoaded('proofOfDelivery')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}