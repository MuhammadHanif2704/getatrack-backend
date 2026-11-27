<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProofOfDelivery extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $hidden = [
        'photo_data',
        'signature_data',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'latitude'     => 'float',
        'longitude'    => 'float',
    ];

    protected $appends = [
        'photo_url',
        'photo_urls',
        'signature_url',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    private function normalizeStoragePath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $clean = trim($path);
        $clean = preg_replace('#^/?storage/(https?://|data:)#i', '$1', $clean);

        if (str_starts_with($clean, 'data:') || preg_match('#^https?://#i', $clean)) {
            return preg_replace('#^http://#i', 'https://', $clean);
        }

        if (preg_match('#/storage/(.+)$#', $clean, $matches)) {
            $clean = $matches[1];
        }

        $clean = preg_replace('#^/?storage/#', '', $clean);
        $clean = ltrim($clean, '/');

        return $clean ? '/storage/' . $clean : null;
    }

    public function getPhotoUrlsAttribute(): array
    {
        $raw = $this->attributes['photo_url'] ?? $this->attributes['photo_path'] ?? null;

        if (!$raw) {
            return [];
        }

        if (is_array($raw)) {
            $items = $raw;
        } else {
            $decoded = json_decode($raw, true);
            $items = is_array($decoded) ? $decoded : [$raw];
        }

        $urls = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $normalized = $this->normalizeStoragePath($item);
                if ($normalized) {
                    $urls[] = $normalized;
                }
            }
        }

        return $urls;
    }

    public function getPhotoUrlAttribute()
    {
        return $this->photo_urls;
    }

    public function getSignatureUrlAttribute(): ?string
    {
        $raw = $this->attributes['signature_url'] ?? $this->attributes['signature_path'] ?? null;
        return $this->normalizeStoragePath($raw);
    }
}