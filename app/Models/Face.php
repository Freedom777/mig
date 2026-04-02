<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Face extends Model
{
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
