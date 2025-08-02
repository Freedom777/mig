<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class ImageGeolocationPoint extends Model
{
    use HasSpatial;

    public $timestamps = false;

    protected $fillable = [
        'image_geolocation_address_id',
        'coordinates',
    ];

    protected $casts = [
        'coordinates' => Point::class,
    ];

    public function address()
    {
        return $this->belongsTo(ImageGeolocationAddress::class, 'image_geolocation_address_id');
    }

    public function images()
    {
        return $this->hasMany(Image::class);
    }

}
