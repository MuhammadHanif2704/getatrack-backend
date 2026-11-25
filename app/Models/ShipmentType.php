<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentType extends Model
{
    protected $fillable = ['code', 'name', 'handling_note', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}