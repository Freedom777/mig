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
    /*
    |--------------------------------------------------------------------------
    | Thumbnails Configuration
    |--------------------------------------------------------------------------
    */
    'thumbnails' => [
        'width' => env('THUMBNAIL_WIDTH', 300),
        'height' => env('THUMBNAIL_HEIGHT', 200),
        'method' => env('THUMBNAIL_METHOD', 'cover'), // cover, scale, resize, contain
        'dir_format' => '{width}x{height}',
        'postfix' => env('THUMBNAIL_POSTFIX', '_thumb_{method}_{width}x{height}'),
    ],
    /*
    |--------------------------------------------------------------------------
    | Paths Configuration
    |--------------------------------------------------------------------------
    */
    'paths' => [
        'disk' => env('IMAGE_DISK', 'private'),
        'images' => env('IMAGE_PATH', 'images'),
        'root' => $storagePath,
        'thumbnails' => env('THUMBNAIL_STORAGE_PATH', 'thumbnails'),
        'debug_subdir' => env('IMAGE_DEBUG_SUBDIR', 'debug'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Configuration
    |--------------------------------------------------------------------------
    |
    | mode:
    |   - 'queue'    : Jobs dispatched to queue (default, production)
    |   - 'sync'     : Jobs executed immediately
    |   - 'disabled' : Skip all processing (maintenance)
    |
    | dry_run:
    |   - true  : Only log what would be done, don't actually execute
    |   - false : Normal execution
    |
    | debug:
    |   - true  : Verbose logging (job params, timing, etc.)
    |   - false : Standard logging
    |
    */
    'processing' => [
        'mode' => env('IMAGE_PROCESSING_MODE', 'queue'),
        'dry_run' => env('IMAGE_PROCESSING_DRY_RUN', false),
        'debug' => env('IMAGE_PROCESSING_DEBUG', false),
        'phash_distance_threshold' => env('PHASH_DISTANCE_THRESHOLD', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Face Recognition
    |--------------------------------------------------------------------------
    */
    'face_api' => [
        'url' => env('FACE_API_URL', 'http://127.0.0.1:5000'),
        'threshold' => env('FACE_RECOGNITION_THRESHOLD', 0.6),
    ],
];
