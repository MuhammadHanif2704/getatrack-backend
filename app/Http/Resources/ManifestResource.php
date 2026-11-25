<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManifestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalItems = $this->details_count ?? ($this->relationLoaded('details') ? $this->details->count() : 0);
        $scannedItems = $this->scanned_details_count ?? ($this->relationLoaded('details')
            ? $this->details->whereNotNull('scanned_at')->count()
            : 0);

        return [
            'id' => $this->id,
            'manifest_number' => $this->manifest_number,
            'courier_id' => $this->courier_id,
            'origin_hub' => $this->origin_hub,
            'destination_hub' => $this->destination_hub,
            'manifest_date' => $this->manifest_date?->format('Y-m-d'),
            'status' => $this->status->value,
            'next_allowed_status' => $this->status->nextAllowedStatus()?->value,
            'notes' => $this->notes,
            'total_shipments' => (int) $totalItems,
            'scanned_shipments' => (int) $scannedItems,
            'courier' => $this->whenLoaded('courier', fn () => [
                'id' => $this->courier->id,
                'name' => $this->courier->name,
                'courier_code' => $this->courier->courier_code,
                'phone' => $this->courier->phone,
                'vehicle_type' => $this->courier->vehicle_type,
                'plate_number' => $this->courier->plate_number,
            ]),
            'details' => ManifestDetailResource::collection($this->whenLoaded('details')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}