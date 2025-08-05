<?php

namespace App\Models;

use App\Casts\BoundingBoxToPolygonCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    public function points()
    {
        return $this->hasMany(ImageGeolocationPoint::class, 'image_geolocation_address_id');
    }

    public function getCityNameAttribute()
    {
        return $this->address['city'] ?? null;
    }

    public static function getCitiesList() : Collection
    {
        return static::query()
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(address, '$.address.city')) as city")
            ->whereNotNull('address')
            ->distinct()
            ->pluck('city')
            ->filter();
    }
}
