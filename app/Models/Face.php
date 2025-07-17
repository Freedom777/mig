<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Face extends Model
{
    use SoftDeletes;

    protected $casts = [
        'encoding' => 'array',
    ];

    protected $fillable = [
        'image_id',
        'face_index',
        'name',
        'encoding',
    ];

    public function images()
    {
        return $this->belongsToMany(Image::class, 'rel_images_faces', 'face_id', 'image_id');
    }
}
