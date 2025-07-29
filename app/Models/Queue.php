<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Casts\HexCast;

class Queue extends Model
{
    public $timestamps = false;

    protected $fillable = ['job_key'];

    protected $casts = [
        'job_key' => HexCast::class,
    ];
}
