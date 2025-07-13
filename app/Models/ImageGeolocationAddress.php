<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImageGeolocationAddress extends Model
{
    public $timestamps = false;

    protected $casts = [
        'address' => 'array',
    ];

    protected $fillable = [
        'address',
    ];

    public function geolocationPoints(): HasMany
    {
        return $this->hasMany(ImageGeolocationPoint::class, 'image_geolocation_address_id');
    }
}
