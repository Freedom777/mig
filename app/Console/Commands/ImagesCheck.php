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
            $imagePath = ImagePathService::getImagePath($image);
            if (!is_file($imagePath)) {
                $this->info('Image (ID: ' . $image->id . ') not exists: ' . $imagePath);
            }

            // Some images very big (PANO_...), so debug image will be not created
            $debugImagePath = ImagePathService::getDebugImagePath($image);
            if (!file_exists($debugImagePath)) {
                $this->info('Debug image (ID: ' . $image->id . ') not exists: ' . $debugImagePath);
            }

            $thumbnailPath = ImagePathService::getDefaultThumbnailPath($image);
            if (!file_exists($thumbnailPath)) {
                $this->info('Thumbnail image (ID: ' . $image->id . ') not exists: ' . $thumbnailPath);
            }

            // $image->status = Image::STATUS_RECHECK;
            // $image->save();

        }
    }
}
