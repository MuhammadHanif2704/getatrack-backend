<?php

namespace App\Http\Requests\Admin;

use App\Enums\ServiceType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'awb_number' => ['nullable', 'string', 'max:40', 'unique:shipments,awb_number'],
            'courier_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) =>$q->where('role', UserRole::COURIER->value)->where('is_active', true)),
            ],
            'sender_name' => ['required', 'string', 'max:100'],
            'sender_phone' => ['required', 'string', 'max:20'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'recipient_phone' => ['required', 'string', 'max:20'],
            'origin_address' => ['required', 'string'],
            'destination_address' => ['required', 'string'],
            'service_type' => ['required', 'string', Rule::in(ServiceType::values())],
            'weight_kg' => ['required', 'numeric', 'min:0.1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'courier_id.exists' => 'Kurir yang dipilih tidak ditemukan atau berstatus nonaktif.',
        ];
    }
}