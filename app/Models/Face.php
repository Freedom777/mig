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

    public function image()
    {
        return $this->belongsTo(Image::class, 'image_id', 'id');
    }

    // Получить всех детей данной записи
    public function children()
    {
        return $this->hasMany(Face::class, 'parent_id');
    }

    // Получить родителя данной записи
    public function parent()
    {
        return $this->belongsTo(Face::class, 'parent_id');
    }
}
