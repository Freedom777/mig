<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class HexCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?string
    {
        return $value ? bin2hex($value) : null;
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        return $value ? hex2bin($value) : null;
    }
}
