<?php

namespace App\Console\Commands;

use App\Jobs\MetadataProcessJob;
use App\Models\Image;
use App\Services\ApiClient;
use App\Traits\QueueAbleTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImagesMetadatas extends Command
{
    use QueueAbleTrait;

    protected $signature = 'images:metadatas {--path : Path to image or directory}';

    public function handle()
    {
        $images = Image::whereNull('metadata')->get();
        foreach ($images as $image) {
            $this->extractMetadata($image->id, $image->disk, $image->path, $image->filename);
        }
    }

    private function extractMetadata(int $imageId, string $diskLabel, string $sourcePath, string $filename)
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
            $response = self::pushToQueue(MetadataProcessJob::class, config('queue.name.metadatas'), [
                'image_id' => $imageId,
            ]);

            $responseData = $response->getData();

            if ($responseData->status === 'success') {
                Log::info('Geolocation job queued', ['image_id' => $imageId]);
            } elseif ($responseData->status === 'exists') {
                Log::info('Geolocation job already in queue', ['image_id' => $imageId]);
            }
        } catch (\Exception $e) {
            $this->error('Failed to send to API (' . $diskLabel . '://' . $sourcePath . '/' . $filename . '): ' . $e->getMessage());
        }


    }
}
