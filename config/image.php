<?php

$storagePath = storage_path('app/public');

return [

    /*
    |--------------------------------------------------------------------------
    | Image Driver
    |--------------------------------------------------------------------------
    |
    | Intervention Image supports “GD Library” and “Imagick” to process images
    | internally. Depending on your PHP setup, you can choose one of them.
    |
    | Included options:
    |   - \Intervention\Image\Drivers\Gd\Driver::class
    |   - \Intervention\Image\Drivers\Imagick\Driver::class
    |
    */

    'driver' => \Intervention\Image\Drivers\Imagick\Driver::class,

    /*
    |--------------------------------------------------------------------------
    | Configuration Options
    |--------------------------------------------------------------------------
    |
    | These options control the behavior of Intervention Image.
    |
    | - "autoOrientation" controls whether an imported image should be
    |    automatically rotated according to any existing Exif data.
    |
    | - "decodeAnimation" decides whether a possibly animated image is
    |    decoded as such or whether the animation is discarded.
    |
    | - "blendingColor" Defines the default blending color.
    |
    | - "strip" controls if meta data like exif tags should be removed when
    |    encoding images.
    */

    'options' => [
        'autoOrientation' => true,
        'decodeAnimation' => true,
        'blendingColor' => 'ffffff',
        'strip' => false,
    ],
    'thumbnails' => [
        'postfix' => env('THUMBNAIL_POSTFIX', '_thumb_{method}_{width}x{height}'), // Можно изменить на '_thumb' если нужно
        'width' => env('THUMBNAIL_WIDTH', 200),
        'height' => env('THUMBNAIL_HEIGHT', 200),
        'dir_format' => '{width}x{height}',
        'method' => env('THUMBNAIL_METHOD', 'cover'),
    ],
    'paths' => [
        'disk' => env('IMAGE_STORAGE_DISK', 'local'),
        'root' => $storagePath,
        'images' => env('IMAGE_STORAGE_PATH', 'images'),
        'thumbnails' => env('THUMBNAIL_STORAGE_PATH', 'thumbnails'),
        'debug_subdir' => env('IMAGE_DEBUG_SUBDIR', 'debug'),
    ],
];
