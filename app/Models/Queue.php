<?php

namespace App\Models;

use App\Casts\HexCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Queue extends Model
{
    public $timestamps = false;

    protected $fillable = ['queue_key'];

    protected $casts = [
        'queue_key' => HexCast::class,
    ];

    /**
     * Scope для поиска по hex-ключу
     * Инкапсулирует логику конвертации hex → binary для where()
     */
    public function scopeByKey(Builder $query, string $hexKey): Builder
    {
        return $query->where('queue_key', hex2bin($hexKey));
    }
}
