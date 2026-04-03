<?php

namespace App\Enums;

enum FaceStatusEnum: string
{
    case Process = 'process'; // В обработке (default)
    case Unknown = 'unknown'; // Неизвестное лицо на фотографии, оно нам не нужно в дальнейшем
    case NotFace = 'not_face'; // Ложное срабатывание face API, это не лицо
    case Suggested = 'suggested'; // Найдено предполагаемое лицо
    case Ok = 'ok'; // Лицо обработано, person_id проставлен

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
