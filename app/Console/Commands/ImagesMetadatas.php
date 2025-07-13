<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Services\ApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImagesMetadatas extends Command
{
    protected $signature = 'images:metadatas {--path : Path to image or directory}';

    protected ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        parent::__construct();
        $this->apiClient = $apiClient;
    }

    public function handle()
    {
        $images = Image::whereNull('metadata')->get();
        foreach ($images as $image) {
            $this->extractMetadata($image->id, $image->disk, $image->path, $image->filename);
        }
    }

    private function extractMetadata(int $id, string $diskLabel, string $sourcePath, string $filename)
    {
        $disk = Storage::disk($diskLabel);
        if (!is_dir($disk->path($sourcePath))) {
            $this->error('It\'s not a directory, ' . $diskLabel . '://' . $sourcePath);
            return 1;
        }

        if (!is_file($disk->path($sourcePath . '/' . $filename))) {
            $this->error('It\'s not a file, ' . $diskLabel . '://' . $sourcePath . '/' . $filename);
            return 1;
        }

        try {
            $requestData = [
                'image_id' => $id,
                'source_disk' => $diskLabel,
                'source_path' => $sourcePath,
                'source_filename' => $filename,
            ];
            $response = $this->apiClient->metadataProcess($requestData);

            if ($response->successful()) {
                $this->info('Task queued: ' . $diskLabel . '://' . $sourcePath . '/' . $filename);
            } else {
                $this->error('API error (' . $diskLabel . '://' . $sourcePath . '/' . $filename . '): ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Failed to send to API (' . $diskLabel . '://' . $sourcePath . '/' . $filename . '): ' . $e->getMessage());
        }


    }
}
