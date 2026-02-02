<?php

namespace App\Models;

class Geolocation
{

    /**
     * Проверяет наличие геоданных в метадате
     *
     * @param array $metadata
     * @return bool
     */
    public static function hasGeodata(array $metadata): bool
    {
        return (isset($metadata['GPSLatitude']) && isset($metadata['GPSLongitude']))
            || isset($metadata['GPSPosition']);
    }

    public static function extractCoordinates(array $metadata): ?array
    {
        if (isset($metadata['GPSLatitude']) && isset($metadata['GPSLongitude'])) {
            return [
                (float)$metadata['GPSLatitude'],
                (float)$metadata['GPSLongitude']
            ];
        }

        if (isset($metadata['GPSPosition'])) {
            $parts = explode(' ', $metadata['GPSPosition']);
            if (count($parts) >= 2) {
                return [
                    (float)$parts[0],
                    (float)$parts[1]
                ];
            }
        }

        return null;
    }
}
