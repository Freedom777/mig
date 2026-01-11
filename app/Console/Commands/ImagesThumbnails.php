<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Services\ApiClient;
use App\Services\ImagePathService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImagesThumbnails extends Command
{
    //                             {disk : Source disk}
    protected $signature = 'images:thumbnails
                            {--width=300 : Max width}
                            {--height=200 : Max height}
                            {--method=cover : Thumbnail decrease method}';


    protected ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        parent::__construct();
        $this->apiClient = $apiClient;
    }

    public function handle()
    {
        $images = Image::whereNull('thumbnail_path')->get();
        $directories = [];

        foreach ($images as $image) {
            $diskLabel = $image->disk;
            $thumbWidth = $this->option('width') ?? config('images.thumbnails.width');
            $thumbHeight = $this->option('height')  ?? config('images.thumbnails.height');
            $thumbPath = ImagePathService::getThumbnailSubdir($thumbWidth, $thumbHeight);
            $thumbFullPath = $image->path . '/' . $thumbPath;

            if (!array_key_exists($diskLabel, $directories)){
                $directories[$image->disk] = [];
            }

            if (!in_array($thumbFullPath, $directories[$diskLabel])) {
                $directories[$diskLabel][] = $thumbFullPath;
                $disk = Storage::disk($diskLabel);
                $disk->makeDirectory($thumbFullPath);
            }

            $this->createThumbnail(
                // $this->argument('disk'),
                $diskLabel,
                $image->path,
                $thumbPath,
                $image->filename,
                $thumbWidth,
                $thumbHeight,
                $this->option('method')  ?? config('images.thumbnails.method'),
            );
        }
    }

    private function createThumbnail(string $diskLabel, string $source, string $thumbPath, string $filename, int $width, int $height, string $method)
    {
        $disk = Storage::disk($diskLabel);
        if (!is_dir($disk->path($source))) {
            $this->error('It\'s not a directory, ' . $diskLabel . '://' . $source);
            return 1;
        }

        if (!is_file($disk->path($source . '/' . $filename))) {
            $this->error('It\'s not a file, ' . $diskLabel . '://' . $source . '/' . $filename);
            return 1;
        }

        $thumbFilename = ImagePathService::getThumbnailFilename($filename, $method, $width, $height);

        try {
            $requestData = [
                'disk' => $diskLabel,
                'source_path' => $source,
                'source_filename' => $filename,
                'thumbnail_path' => $thumbPath,
                'thumbnail_filename' => $thumbFilename,
                'thumbnail_method' => $method,
                'thumbnail_width' => $width,
                'thumbnail_height' => $height,
            ];
            $response = $this->apiClient->thumbnailProcess($requestData);

            if ($response->successful()) {
                $this->info('Task queued: ' . $diskLabel . '://' . $source . '/' . $thumbPath . '/' . $thumbFilename);
            } else {
                $this->error('API error ' . $diskLabel . '://' . $source . '/' . $thumbPath . '/' . $thumbFilename . ': ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Failed to send to API: ' . $e->getMessage());
        }
    }
}
