<?php

namespace App\Models;

use App\Enums\ManifestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manifest extends Model
{
    use HasFactory;

    protected $fillable = [
        'manifest_number',
        'courier_id',
        'origin_hub',
        'destination_hub',
        'manifest_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'manifest_date' => 'date:Y-m-d',
            'status' => ManifestStatus::class,
        ];
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ManifestDetail::class);
    }

    public function shipments(): BelongsToMany
    {
        return $this->belongsToMany(Shipment::class, 'manifest_details')
            ->withPivot(['id', 'scanned_at'])
            ->withTimestamps();
    }

    public function scopeForCourier(Builder $query, int $courierId): Builder
    {
        return $query->where('courier_id', $courierId);
    }
}