<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    protected $fillable = ['code', 'name', 'sla_days', 'base_rate', 'description', 'is_active'];

    protected $casts = [
        'base_rate' => 'float',
        'is_active' => 'boolean',
    ];
}