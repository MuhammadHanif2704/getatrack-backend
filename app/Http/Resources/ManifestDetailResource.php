<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManifestDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'manifest_id' => $this->manifest_id,
            'shipment_id' => $this->shipment_id,
            'is_scanned' => $this->scanned_at !== null,
            'scanned_at' => $this->scanned_at?->toIso8601String(),
            'shipment' => new ShipmentResource($this->whenLoaded('shipment')),
        ];
    }
}