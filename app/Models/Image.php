<?php

namespace App\Models;

use App\Casts\HexCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Image extends Model
{
    public const STATUS_PROCESS = 'process';
    public const STATUS_NOT_PHOTO = 'not_photo';
    public const STATUS_RECHECK = 'recheck';
    public const STATUS_OK = 'ok';

    protected $casts = [
        'metadata' => 'array',
        'faces_checked' => 'boolean',
        'created_at_file' => 'datetime',
        'updated_at_file' => 'datetime',
        'hash' => HexCast::class,
        'phash' => HexCast::class,
    ];

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

    // ==========================================
    // Relationships
    // ==========================================

    public function geolocationPoint(): BelongsTo
    {
        return $this->belongsTo(ImageGeolocationPoint::class);
    }

    public function geolocationAddress(): HasOneThrough
    {
        return $this->hasOneThrough(
            ImageGeolocationAddress::class,
            ImageGeolocationPoint::class,
            'id',
            'id',
            'image_geolocation_point_id',
            'image_geolocation_address_id'
        );
    }

    public function faces(): HasMany
    {
        return $this->hasMany(Face::class, 'image_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Image::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'parent_id');
    }

    // ==========================================
    // Query Scopes / Static Query Methods
    // ==========================================

    /**
     * Найти предыдущее изображение
     */
    public static function previous(int $id, ?string $status = null): ?self
    {
        $query = static::whereNull('parent_id')
            ->where('id', '<', $id)
            ->orderBy('id', 'desc'); // FIX: должен быть desc для "предыдущего"

        if ($status) {
            $query->where('status', $status);
        }

        return $query->first();
    }

    /**
     * Найти следующее изображение
     */
    public static function next(int $id, ?string $status = null): ?self
    {
        $query = static::whereNull('parent_id')
            ->where('id', '>', $id)
            ->orderBy('id', 'asc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->first();
    }

    // ==========================================
    // REMOVED: Бизнес-логика перенесена в ImageRepository
    // - prepareData() -> ImageRepository::prepareImageData()
    // - updateInsert() -> ImageRepository::updateOrCreate()
    // - findSimilarImageId() -> ImageRepository::findSimilarByPhash()
    // ==========================================
}
