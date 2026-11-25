<?php

namespace App\Http\Requests\Manifest;

use App\Enums\ManifestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManifestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ManifestStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status manifest baru wajib dipilih.',
            'status.in' => 'Status manifest tidak valid.',
        ];
    }
}