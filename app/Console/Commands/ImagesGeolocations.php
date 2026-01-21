<?php

namespace App\Console\Commands;

use App\Jobs\GeolocationProcessJob;
use App\Models\Image;
use App\Traits\QueueAbleTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImagesGeolocations extends Command
{
    use QueueAbleTrait;

    protected $signature = 'images:geolocations';

    protected $description = 'Put all geolocations, which are not processed and have geodata in the metadata to queue.';

    public function handle()
    {
        Image::query()
            ->select('id')
            ->whereNotNull('metadata')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('metadata->GPSLatitude')
                        ->whereNotNull('metadata->GPSLongitude');
                })
                    ->orWhereNotNull('metadata->GPSPosition');
            })
            ->whereNull('image_geolocation_point_id')
            ->chunkById(1000, function ($images) {
                foreach ($images as $image) {
                    $response = self::pushToQueue(GeolocationProcessJob::class, config('queue.name.geolocations'), [
                        'image_id' => $image->id,
                    ]);

                    $responseData = $response->getData();

                    if ($responseData->status === 'success') {
                        Log::info('Geolocation job queued', ['image_id' => $image->id]);
                    } elseif ($responseData->status === 'exists') {
                        Log::info('Geolocation job already in queue', ['image_id' => $image->id]);
                    }

                }
            });

    }
}
