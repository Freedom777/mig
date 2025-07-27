<?php

namespace App\Console\Commands;

use App\Models\Image;
use Illuminate\Console\Command;
use App\Services\ApiClient;

class ImagesFaces extends Command
{
    protected $signature = 'images:faces';
    protected ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        parent::__construct();
        $this->apiClient = $apiClient;
    }

    public function handle()
    {
        $images = Image::where('faces_checked', 0)->get();

        foreach ($images as $image) {
            $this->extractFaces($image->id);
        }
    }

    private function extractFaces(int $id)
    {
        try {
            $requestData = [
                'image_id' => $id,
            ];
            $response = $this->apiClient->faceProcess($requestData);

            if ($response->successful()) {
                $this->info('Task queued, image ID: ' . $id);
            } else {
                $this->error('API error (image ID: ' . $id . '): ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Failed to send to API (image ID: ' . $id . '): ' . $e->getMessage());
        }
    }
}
