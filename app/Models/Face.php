<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Face extends Model
{
    const STATUS_PROCESS = 'process';
    const STATUS_UNKNOWN = 'unknown';
    const STATUS_NOT_FACE = 'not_face';
    const STATUS_OK = 'ok';

    use SoftDeletes;

    protected $casts = [
        'encoding' => 'array',
    ];

    protected $fillable = [
        'image_id',
        'face_index',
        'name',
        'encoding',
        'status',
    ];

    public function images()
    {
        return $this->belongsToMany(Image::class, 'rel_images_faces', 'face_id', 'image_id');
    }
}
