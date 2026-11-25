<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminManifestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'manifest_number' => ['nullable', 'string', 'max:40', 'unique:manifests,manifest_number'],
            'courier_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', UserRole::COURIER->value)->where('is_active', true)),
            ],
            'origin_hub' => ['required', 'string', 'max:100'],
            'destination_hub' => ['required', 'string', 'max:100'],
            'manifest_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'shipment_ids' => ['required', 'array', 'min:1'],
            'shipment_ids.*' => ['integer', 'distinct', 'exists:shipments,id'],
        ];
    }
}