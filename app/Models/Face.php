<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Face extends Model
{
    protected $casts = [
        'encoding' => 'array',
    ];

    protected $fillable = [
        'name',
        'encoding',
    ];

    public function images()
    {
        return $this->belongsToMany(Image::class, 'rel_images_faces', 'face_id', 'image_id');
    }
}
