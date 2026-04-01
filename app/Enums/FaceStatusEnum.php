<?php

namespace App\Enums;

enum FaceStatusEnum: string
{
    case Process = 'process';

    case Check = 'check';
    case Unknown = 'unknown';
    case NotFace = 'not_face';
    case Ok = 'ok';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Для передачи во Vue: {NotFace: 'not_face', Ok: 'ok', ...}
     */
    public static function toObject(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->name] = $case->value;
        }
        return $result;
    }
}
