<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Services\ImagePathService;
use Illuminate\Console\Command;

class ImagesCheck extends Command
{
    protected $signature = 'images:check';

    public function handle()
    {
        $images = Image::all();

        foreach ($images as $image) {
            $imagePath = $image->filename
                ? ImagePathService::getImagePath($image)
                : null;

            if (!$imagePath || !is_file($imagePath)) {
                $this->info(sprintf(
                    'Image (ID: %d) not exists: %s',
                    $image->id,
                    $imagePath ?? '[no filename]'
                ));
            }

            // Some images very big (PANO_...), so debug image will be not created
            $debugImagePath = $image->debug_filename
                ? ImagePathService::getDebugImagePath($image)
                : null;

            if (!$debugImagePath || !is_file($debugImagePath)) {
                $this->info(sprintf(
                    'Debug image (ID: %d) not exists: %s',
                    $image->id,
                    $debugImagePath ?? '[no filename]'
                ));
            }

            $thumbnailPath = $image->thumbnail_filename
                ? ImagePathService::getDefaultThumbnailPath($image)
                : null;

            if (!$thumbnailPath || !is_file($thumbnailPath)) {
                $this->info(sprintf(
                    'Thumbnail image (ID: %d) not exists: %s',
                    $image->id,
                    $thumbnailPath ?? '[no filename]'
                ));
            }

            // $image->status = Image::STATUS_RECHECK;
            // $image->save();

        }
    }
}
