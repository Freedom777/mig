<?php

namespace App\Models;

use App\Traits\QueueAbleTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;

class Image extends Model
{
    use QueueAbleTrait;

    const STATUS_PROCESS = 'process';

    const STATUS_NOT_PHOTO = 'not_photo';

    const STATUS_RECHECK = 'recheck'; // When on photo face exists, but not recognized
    const STATUS_OK = 'ok';

    protected $fillable = [
        'parent_id',
        'image_geolocation_point_id',

        'disk',

        'path',
        'filename',
        'debug_filename',
        'width',
        'height',
        'size',
        'hash',
        'phash',
        'created_at_file',
        'updated_at_file',

        'metadata',
        'faces_checked',

        'thumbnail_path',
        'thumbnail_filename',
        'thumbnail_method',
        'thumbnail_width',
        'thumbnail_height',

        'status',
        'last_error'
    ];

    // Мутатор для записи
    public function setHashAttribute($value)
    {
        $this->attributes['hash'] = hex2bin($value);
    }

    // Акцессор для чтения
    public function getHashAttribute($value)
    {
        return bin2hex($value);
    }

    public function setPhashAttribute($value)
    {
        $this->attributes['phash'] = hex2bin($value);
    }

    // Акцессор для чтения
    public function getPhashAttribute($value)
    {
        return bin2hex($value);
    }

    public function geolocationPoint()
    {
        return $this->belongsTo(ImageGeolocationPoint::class);
    }

    public function geolocationAddress()
    {
        return $this->hasOneThrough(
            ImageGeolocationAddress::class,
            ImageGeolocationPoint::class,
            'id',                           // Foreign key on points table
            'id',                           // Foreign key on addresses table
            'image_geolocation_point_id',   // Local key on images table
            'image_geolocation_address_id'  // Local key on points table
        );
    }

    public function faces()
    {
        return $this->hasMany(Face::class, 'image_id', 'id');
    }

    public static function previous($id, $status = null)
    {
        $image = static::whereNull('parent_id')->where('id', '<', $id)
            ->orderBy('id', 'asc');
        if ($status) {
            $image = $image->where('status', $status);
        }
        return $image->first();
    }

    public static function next($id, $status = null)
    {
        $image = static::whereNull('parent_id')->where('id', '>', $id)
            ->orderBy('id', 'asc');
        if ($status) {
            $image = $image->where('status', $status);
        }
        return $image->first();
    }

    public function children()
    {
        return $this->hasMany(Image::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Image::class, 'parent_id');
    }

    public static function findSimilarImageId(string $hexHash, int $maxDistance = 5): ?int
    {
        return self::query()
            ->whereRaw('BIT_COUNT(phash ^ UNHEX(?)) < ?', [$hexHash, $maxDistance])
            ->orderByRaw('BIT_COUNT(phash ^ UNHEX(?)) ASC', [$hexHash])
            ->limit(1)
            ->value('id');
    }

    public static function prepareData(string $diskLabel, string $sourcePath, string $filename) : array {
        $disk = Storage::disk($diskLabel);
        $filePath = $disk->path($sourcePath) . '/' . $filename;

        $existImageFlag = Image::where([
            'disk' => $diskLabel,
            'path' => $sourcePath,
            'filename' => $filename,
        ])->exists();

        $md5 = md5_file($filePath);
        $hasher = new ImageHash(new PerceptualHash());
        $phashCurrent = $hasher->hash($filePath);
        $imageData = getimagesize($filePath);

        $duplicateId = null;
        if (!$existImageFlag) {
            // Проверяем, есть ли уже такое изображение в базе
            $duplicateId = Image::where('hash', $md5)->value('id');
            if (!$duplicateId) {
                $phashCurrentHex = $phashCurrent->toHex();
                $duplicateId = Image::findSimilarImageId($phashCurrentHex);
            }
        }

        return [
            'parent_id' => $duplicateId,
            'source_disk' => $diskLabel,
            'source_path' => $sourcePath,
            'source_filename' => $filename,
            'width' => $imageData[0],
            'height' => $imageData[1],
            'size' => filesize($filePath),
            'hash' => $md5,
            'phash' => $phashCurrent,
            'created_at_file' => date('Y-m-d H:i:s', filectime($filePath)),
            'updated_at_file' => date('Y-m-d H:i:s', filemtime($filePath)),
        ];
    }

    public static function updateInsert(array $imageData) : Image|null
    {
        $imagePath = $imageData['source_path'] . '/' . $imageData['source_filename'];

        try {
            $image = Image::updateOrCreate(
                [
                    'disk' => $imageData['source_disk'],
                    'path' => $imageData['source_path'],
                    'filename' => $imageData['source_filename']
                ],
                [
                    'parent_id' => $imageData['parent_id'],
                    'width' => $imageData['width'],
                    'height' => $imageData['height'],
                    'size' => $imageData['size'],
                    'hash' => $imageData['hash'],
                    'phash' => $imageData['phash'],
                    'created_at_file' => $imageData['created_at_file'],
                    'updated_at_file' => $imageData['updated_at_file'],
                ]
            );

            Log::info('Processed: ' . $imagePath);

            return $image;
        } catch (\Exception $e) {
            Log::error('Failed to process image ' . $imagePath . ': ' . $e->getMessage());
            return null;
        }
    }

}
