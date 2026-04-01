<?php

namespace App\Enums;

enum ImageStatusEnum: string
{
    case Process = 'process';
    case NotPhoto = 'not_photo';
    case Recheck = 'recheck';
    case Ok = 'ok';

    public function label(): string
    {
        return match($this) {
            self::Process => 'В обработке',
            self::NotPhoto => 'Не является фотографией',
            self::Recheck => 'На перепроверку',
            self::Ok => 'Обработано',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
