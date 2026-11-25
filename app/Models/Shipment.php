<?php

namespace App\Models;

use App\Enums\ServiceType;
use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'awb_number',
        'courier_id',
        'sender_name',
        'sender_phone',
        'recipient_name',
        'recipient_phone',
        'origin_address',
        'destination_address',
        'service_type',
        'item_description',
        'shipment_type',
        'weight_kg',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'status' => ShipmentStatus::class,
            'weight_kg' => 'decimal:2',
        ];
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function manifestDetails(): HasMany
    {
        return $this->hasMany(ManifestDetail::class);
    }

    public function manifests(): BelongsToMany
    {
        return $this->belongsToMany(Manifest::class, 'manifest_details')
            ->withPivot(['id', 'scanned_at'])
            ->withTimestamps();
    }

    public function trackingLogs(): HasMany
    {
        return $this->hasMany(TrackingLog::class)->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    public function proofOfDelivery(): HasOne
    {
        return $this->hasOne(ProofOfDelivery::class);
    }

    public function scopeForCourier(Builder $query, int $courierId): Builder
    {
        return $query->where('courier_id', $courierId);
    }
}