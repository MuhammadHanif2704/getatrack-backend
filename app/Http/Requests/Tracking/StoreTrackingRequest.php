<?php

namespace App\Http\Requests\Tracking;

use App\Enums\ShipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ShipmentStatus::trackingUpdatableValues())],
            'description' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status pengiriman baru wajib dipilih.',
            'status.in' => 'Status yang dipilih tidak valid untuk pembaruan tracking.',
            'description.required' => 'Keterangan aktivitas pengiriman wajib diisi.',
        ];
    }
}