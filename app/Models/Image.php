<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Image extends Model
{
    const STATUS_PROCESS = 'process';
    const STATUS_OK = 'ok';

    protected $fillable = [
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

    public function geolocationPoint(): BelongsTo
    {
        return $this->belongsTo(ImageGeolocationPoint::class, 'geolocation_point_id');
    }

    public function faces()
    {
        return $this->belongsToMany(Face::class, 'rel_images_faces', 'image_id', 'face_id');
    }

    public function previous()
    {
        return static::where('id', '<', $this->id)
            ->orderBy('id', 'desc')
            ->first();
    }

    public function next()
    {
        return static::where('id', '>', $this->id)
            ->orderBy('id', 'asc')
            ->first();
    }
}
