<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $fillable = [
        'contact_category_id',
        'city_id',
        'code',
        'name',
        'company_name',
        'phone',
        'email',
        'address',
        'postal_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContactCategory::class, 'contact_category_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}