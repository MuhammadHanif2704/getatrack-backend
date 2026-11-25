<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_id' => $this->shipment_id,
            'recipient_name' => $this->recipient_name,
            'recipient_relation' => $this->recipient_relation,
            'photo_url' => $this->photo_url,
            'signature_url' => $this->signature_url,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'notes' => $this->notes,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'courier' => $this->whenLoaded('courier', fn () => [
                'id' => $this->courier->id,
                'name' => $this->courier->name,
                'courier_code' => $this->courier->courier_code,
            ]),
        ];
    }
}