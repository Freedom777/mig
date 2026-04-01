<?php

namespace App\Models;

use App\Enums\FaceStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    protected $table = 'persons';

    protected $fillable = [
        'name',
        'photo',
        'centroid_embedding',
        'embeddings_count',
    ];

    protected $casts = [
        'centroid_embedding' => 'array',
    ];

    public function faces(): HasMany
    {
        return $this->hasMany(Face::class);
    }

    public function confirmedFaces(): HasMany
    {
        return $this->hasMany(Face::class)->where('status', FaceStatusEnum::Ok->value->value());
    }

    public function referenceFaces(): HasMany
    {
        return $this->hasMany(Face::class)->where('is_reference', true);
    }
}
