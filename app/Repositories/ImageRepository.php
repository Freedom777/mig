<?php

namespace App\Repositories;

use App\Contracts\ImageRepositoryInterface;
use App\Models\Image;
use App\Models\ImageGeolocationPoint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MatanYadaev\EloquentSpatial\Objects\Point;

class ImageRepository implements ImageRepositoryInterface
{
    /**
     * Подготовка данных для создания/обновления изображения
     */
    public function prepareImageData(string $disk, string $path, string $filename): array
    {
        $diskInstance = Storage::disk($disk);
        $filePath = $diskInstance->path($path) . '/' . $filename;

        $data = [
            'source_disk' => $disk,
            'source_path' => $path,
            'source_filename' => $filename,
            'size' => filesize($filePath),
            'created_at_file' => date('Y-m-d H:i:s', filectime($filePath)),
            'updated_at_file' => date('Y-m-d H:i:s', filemtime($filePath)),
        ];

        // Читаем EXIF через exif_read_data из JPG/JPEG (быстро ~50ms)
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extension, ['jpg', 'jpeg']) && file_exists($filePath)) {
            try {
                $exif = @exif_read_data($filePath, null, true);

                if ($exif) {
                    // 1. Дата съёмки (приоритет полей)
                    $data['taken_at'] = $this->extractTakenAt($exif);

                    // 2. GPS координаты → создаём/находим point
                    if (isset($exif['GPS']['GPSLatitude']) && isset($exif['GPS']['GPSLongitude'])) {
                        $pointId = $this->createOrFindGpsPoint($exif['GPS']);
                        if ($pointId) {
                            $data['image_geolocation_point_id'] = $pointId;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to read EXIF with exif_read_data', [
                    'filename' => $filename,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $data;
    }

    /**
     * Извлечь дату съёмки из EXIF
     */
    private function extractTakenAt(array $exif): ?string
    {
        // Приоритет: DateTimeOriginal → CreateDate → DateTime
        if (isset($exif['EXIF']['DateTimeOriginal'])) {
            return $this->convertExifDateTime($exif['EXIF']['DateTimeOriginal']);
        }

        if (isset($exif['EXIF']['CreateDate'])) {
            return $this->convertExifDateTime($exif['EXIF']['CreateDate']);
        }

        if (isset($exif['IFD0']['DateTime'])) {
            return $this->convertExifDateTime($exif['IFD0']['DateTime']);
        }

        return null;
    }

    /**
     * Конвертировать EXIF дату в MySQL формат
     */
    private function convertExifDateTime(string $exifDate): ?string
    {
        try {
            // EXIF формат: "2026:04:13 22:39:07"
            $timestamp = strtotime($exifDate);
            if ($timestamp === false) {
                return null;
            }
            return date('Y-m-d H:i:s', $timestamp);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Создать или найти GPS point
     */
    private function createOrFindGpsPoint(array $gps): ?int
    {
        try {
            // Конвертируем GPS в decimal
            $latitude = $this->convertGpsToDecimal(
                $gps['GPSLatitude'],
                $gps['GPSLatitudeRef']
            );

            $longitude = $this->convertGpsToDecimal(
                $gps['GPSLongitude'],
                $gps['GPSLongitudeRef']
            );

            if ($latitude === null || $longitude === null) {
                return null;
            }

            // Создаём Point объект
            $pointLatLon = new Point($latitude, $longitude);

            // Находим или создаём point
            $point = ImageGeolocationPoint::firstOrCreate([
                'coordinates' => $pointLatLon
            ]);

            Log::info('GPS point created or found', [
                'point_id' => $point->id,
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);

            return $point->id;

        } catch (\Exception $e) {
            Log::error('Failed to create GPS point', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Конвертировать GPS координату из degrees/minutes/seconds в decimal
     */
    private function convertGpsToDecimal(array $coordinate, string $ref): ?float
    {
        try {
            if (count($coordinate) < 3) {
                return null;
            }

            // Парсим градусы, минуты, секунды
            $degrees = $this->evalFraction($coordinate[0]);
            $minutes = $this->evalFraction($coordinate[1]);
            $seconds = $this->evalFraction($coordinate[2]);

            // Конвертируем в decimal
            $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

            // Применяем знак для южной/западной полусфер
            if (in_array($ref, ['S', 'W'])) {
                $decimal = -$decimal;
            }

            return round($decimal, 7);

        } catch (\Exception $e) {
            Log::error('GPS conversion failed', [
                'coordinate' => $coordinate,
                'ref' => $ref,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Вычислить дробь из строки вида "123/456" или число
     */
    private function evalFraction($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && strpos($value, '/') !== false) {
            $parts = explode('/', $value);
            if (count($parts) === 2 && $parts[1] != 0) {
                return (float) $parts[0] / (float) $parts[1];
            }
        }

        return 0;
    }

    /**
     * Проверка существования изображения в БД
     */
    public function exists(string $disk, string $path, string $filename): bool
    {
        return Image::where([
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
        ])->exists();
    }

    /**
     * Создать или обновить запись изображения
     */
    public function updateOrCreate(array $imageData): ?Image
    {
        $imagePath = $imageData['source_path'] . '/' . $imageData['source_filename'];

        try {
            $updateData = [
                'size' => $imageData['size'],
                'created_at_file' => $imageData['created_at_file'],
                'updated_at_file' => $imageData['updated_at_file'],
            ];

            // Добавляем taken_at если есть
            if (isset($imageData['taken_at'])) {
                $updateData['taken_at'] = $imageData['taken_at'];
            }

            // Добавляем image_geolocation_point_id если есть
            if (isset($imageData['image_geolocation_point_id'])) {
                $updateData['image_geolocation_point_id'] = $imageData['image_geolocation_point_id'];
            }

            $image = Image::updateOrCreate(
                [
                    'disk' => $imageData['source_disk'],
                    'path' => $imageData['source_path'],
                    'filename' => $imageData['source_filename']
                ],
                $updateData
            );

            Log::info('Image processed', ['path' => $imagePath, 'id' => $image->id]);

            return $image;
        } catch (\Exception $e) {
            Log::error('Failed to process image', [
                'path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Найти изображение по ID
     */
    public function find(int $id): ?Image
    {
        return Image::find($id);
    }

    /**
     * Найти изображение по ID или выбросить исключение
     */
    public function findOrFail(int $id): Image
    {
        return Image::findOrFail($id);
    }

    /**
     * Найти похожее изображение по perceptual hash
     */
    public function findSimilarByPhash(string $hexHash, int $maxDistance = 5): ?int
    {
        return Image::query()
            ->whereRaw('BIT_COUNT(phash ^ UNHEX(?)) < ?', [$hexHash, $maxDistance])
            ->orderByRaw('BIT_COUNT(phash ^ UNHEX(?)) ASC', [$hexHash])
            ->limit(1)
            ->value('id');
    }
}
