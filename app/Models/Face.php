<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'quality_details' => 'array',
        'is_reference' => 'boolean',
    ];

    protected $fillable = [
        'image_id',
        'face_index',
        'name',
        'encoding',
        'person_id',
        'quality_score',
        'quality_details',
        'is_reference',
        'status',
    ];

    public function image()
    {
        return $this->belongsTo(Image::class, 'image_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(Face::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Face::class, 'parent_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
