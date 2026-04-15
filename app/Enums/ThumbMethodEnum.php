<?php

namespace App\Enums;

enum ThumbMethodEnum: string
{
    case Cover = 'cover';
    case Scale = 'scale';
    case Resize = 'resize';
    case Contain = 'contain';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
