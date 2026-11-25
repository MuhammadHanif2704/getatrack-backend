<?php

namespace App\Http\Requests\Manifest;

use Illuminate\Foundation\Http\FormRequest;

class ScanManifestAwbRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'awb_number' => ['required', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'awb_number.required' => 'Nomor AWB wajib diisi atau dipindai.',
        ];
    }
}