<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImageGeolocationPoint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'image_geolocation_address_id',

        'coordinates',
    ];

    public function address(): BelongsTo
    {
        return $this->belongsTo(ImageGeolocationAddress::class, 'image_geolocation_address_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class, 'image_geolocation_point_id');
    }
}
