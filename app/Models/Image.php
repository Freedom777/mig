<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    const STATUS_PROCESS = 'process';

    const STATUS_NOT_PHOTO = 'not_photo';

    const STATUS_RECHECK = 'recheck'; // When on photo face exists, but not recognized
    const STATUS_OK = 'ok';

    protected $fillable = [
        'parent_id',
        'image_geolocation_point_id',

        'disk',

        'path',
        'filename',
        'debug_filename',
        'width',
        'height',
        'size',
        'hash',
        'created_at_file',
        'updated_at_file',

        'metadata',
        'faces_checked',

        'thumbnail_path',
        'thumbnail_filename',
        'thumbnail_method',
        'thumbnail_width',
        'thumbnail_height',

        'status',
        'last_error'
    ];

    // Мутатор для записи
    public function setHashAttribute($value)
    {
        $this->attributes['hash'] = hex2bin($value);
    }

    // Акцессор для чтения
    public function getHashAttribute($value)
    {
        return bin2hex($value);
    }

    public function geolocationPoint()
    {
        return $this->belongsTo(ImageGeolocationPoint::class);
    }

    public function geolocationAddress()
    {
        return $this->hasOneThrough(
            ImageGeolocationAddress::class,
            ImageGeolocationPoint::class,
            'id',                           // Foreign key on points table
            'id',                           // Foreign key on addresses table
            'image_geolocation_point_id',   // Local key on images table
            'image_geolocation_address_id'  // Local key on points table
        );
    }

    public function faces()
    {
        return $this->hasMany(Face::class, 'image_id', 'id');
    }

    public static function previous($id, $status = null)
    {
        $image = static::whereNull('parent_id')->where('id', '<', $id)
            ->orderBy('id', 'asc');
        if ($status) {
            $image = $image->where('status', $status);
        }
        return $image->first();
    }

    public static function next($id, $status = null)
    {
        $image = static::whereNull('parent_id')->where('id', '>', $id)
            ->orderBy('id', 'asc');
        if ($status) {
            $image = $image->where('status', $status);
        }
        return $image->first();
    }

    public function children()
    {
        return $this->hasMany(Image::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Image::class, 'parent_id');
    }

}
