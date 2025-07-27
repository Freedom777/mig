<?php

namespace App\Models;

use App\Casts\BoundingBoxToPolygonCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class ImageGeolocationAddress extends Model
{
    use HasSpatial;

    public $timestamps = false;

    protected $casts = [
        'address' => 'array',
        'osm_area' => BoundingBoxToPolygonCast::class,
    ];

    protected $fillable = [
        'osm_id',
        'osm_area',
        'address',
    ];

    public function geolocationPoints(): HasMany
    {
        return $this->hasMany(ImageGeolocationPoint::class, 'image_geolocation_address_id');
    }
}
