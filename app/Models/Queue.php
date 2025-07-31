<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Casts\HexCast;

class Queue extends Model
{
    public $timestamps = false;

    protected $fillable = ['queue_key'];

    protected $casts = [
        'queue_key' => HexCast::class,
    ];
}
