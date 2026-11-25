<?php

namespace App\Http\Requests\Pod;

use Illuminate\Foundation\Http\FormRequest;

class StorePodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:100'],
            'recipient_relation' => ['nullable', 'string', 'max:50'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'signature' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_name.required' => 'Nama penerima paket wajib diisi.',
            'photo.required' => 'Foto bukti pengiriman wajib dilampirkan.',
            'photo.image' => 'File bukti pengiriman harus berupa gambar.',
            'photo.max' => 'Ukuran foto bukti pengiriman maksimal 5 MB.',
            'signature.required' => 'Tanda tangan digital penerima wajib diisi.',
            'signature.max' => 'Ukuran file tanda tangan maksimal 2 MB.',
        ];
    }
}