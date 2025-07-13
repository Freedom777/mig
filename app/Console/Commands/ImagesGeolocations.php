<?php

namespace App\Console\Commands;

use App\Models\Image;
use Illuminate\Console\Command;
use App\Services\ApiClient;

class ImagesGeolocations extends Command
{
    protected $signature = 'images:geolocations';
    protected ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        parent::__construct();
        $this->apiClient = $apiClient;
    }

    public function handle()
    {
        $images = Image::whereNotNull('metadata')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('metadata->GPSLatitude')
                        ->whereNotNull('metadata->GPSLongitude');
                })
                    ->orWhereNotNull('metadata->GPSPosition');
            })
            ->whereNull('image_geolocation_point_id')
            ->get();

        foreach ($images as $image) {
            $this->extractGeolocation($image->id, $image->metadata);
        }
    }

    private function extractGeolocation(int $id, string $metadata)
    {
        try {
            $requestData = [
                'image_id' => $id,
                'metadata' => $metadata,
            ];
            $response = $this->apiClient->geolocationProcess($requestData);

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
