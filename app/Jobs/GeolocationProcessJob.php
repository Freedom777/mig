<?php

namespace App\Jobs;

use App\Models\Image;
use App\Models\ImageGeolocationAddress;
use App\Models\ImageGeolocationPoint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeolocationProcessJob extends BaseProcessJob
{
    /**
     * Rate limit для Nominatim API (1 запрос в секунду)
     */
    private const NOMINATIM_RATE_LIMIT_SECONDS = 2;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $imageId = $this->taskData['image_id'];
        $lockKey = 'geolocation-processing:' . $imageId;
        $lock = Cache::lock($lockKey, 10);

        try {
            $lock->block(10, function () {
                $this->processGeolocation();
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Could not acquire lock for geolocation processing', [
                'image_id' => $imageId
            ]);
            $this->release(30);
            return;
        } catch (\Exception $e) {
            Log::error('Geolocation processing failed', [
                'image_id' => $imageId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            $this->complete();
        }
    }

    private function processGeolocation(): void
    {
        $image = Image::findOrFail($this->taskData['image_id']);

        // Проверяем наличие point
        if (!$image->image_geolocation_point_id) {
            Log::warning('No geolocation point for image', ['image_id' => $image->id]);
            return;
        }

        // Получаем point
        $point = ImageGeolocationPoint::find($image->image_geolocation_point_id);

        if (!$point) {
            Log::error('Geolocation point not found', [
                'image_id' => $image->id,
                'point_id' => $image->image_geolocation_point_id
            ]);
            return;
        }

        // Если у point уже есть address - ничего не делаем
        if ($point->image_geolocation_address_id) {
            Log::info('Point already has address', [
                'image_id' => $image->id,
                'point_id' => $point->id,
                'address_id' => $point->image_geolocation_address_id
            ]);
            return;
        }

        // Извлекаем координаты из point
        $latitude = $point->coordinates->latitude;
        $longitude = $point->coordinates->longitude;

        Log::info('Processing geolocation for point', [
            'image_id' => $image->id,
            'point_id' => $point->id,
            'coordinates' => [$latitude, $longitude]
        ]);

        // Ищем существующий address по области
        $addressId = ImageGeolocationAddress::whereContains('osm_area', $point->coordinates)->value('id');

        // Если не нашли - запрашиваем у Nominatim
        if (!$addressId) {
            $this->waitForRateLimit();

            $addressId = $this->getAddressId($latitude, $longitude);
            if (!$addressId) {
                throw new \Exception('Failed to get address from Nominatim API');
            }
        }

        // Привязываем address к point
        $point->update(['image_geolocation_address_id' => $addressId]);

        Log::info('Geolocation processed successfully', [
            'image_id' => $image->id,
            'point_id' => $point->id,
            'address_id' => $addressId
        ]);
    }

    /**
     * Ожидает перед вызовом Nominatim API для соблюдения rate limit
     */
    private function waitForRateLimit(): void
    {
        $lockKey = 'nominatim-api-rate-limit';
        $lastCallTime = Cache::get($lockKey);

        if ($lastCallTime) {
            $timeSinceLastCall = microtime(true) - $lastCallTime;
            $waitTime = self::NOMINATIM_RATE_LIMIT_SECONDS - $timeSinceLastCall;

            if ($waitTime > 0) {
                $waitMicroseconds = (int)($waitTime * 1000000);
                Log::debug('Waiting for Nominatim rate limit', [
                    'wait_seconds' => round($waitTime, 3)
                ]);
                usleep($waitMicroseconds);
            }
        }

        Cache::put($lockKey, microtime(true), 5);
    }

    private function getAddressId(float $latitude, float $longitude): false|int
    {
        $locUrl = Str::of(config('app.geolocation_api_url'))
            ->replace('{latitude}', $latitude)
            ->replace('{longitude}', $longitude)
            ->__toString();

        try {
            $response = Http::timeout(10)
                ->retry(3, 2000)
                ->withHeaders(['User-Agent' => 'MyGeocodingApp/1.0'])
                ->get($locUrl);

            if (!$response->ok()) {
                Log::error('Nominatim API request failed', [
                    'status' => $response->status(),
                    'url' => $locUrl,
                    'body' => $response->body()
                ]);
                return false;
            }

            $addressAr = $response->json();

            if (!is_array($addressAr) || empty($addressAr['boundingbox'])) {
                Log::error('Invalid geolocation response', [
                    'response' => $response->body()
                ]);
                return false;
            }

            $existingAddress = ImageGeolocationAddress::where('osm_id', $addressAr['osm_id'])->first();
            if ($existingAddress) {
                Log::info('Using existing address', ['address_id' => $existingAddress->id]);
                return $existingAddress->id;
            }

            return $this->saveAddress($addressAr);

        } catch (\Exception $e) {
            Log::error('Nominatim API error', [
                'error' => $e->getMessage(),
                'coordinates' => [$latitude, $longitude]
            ]);
            return false;
        }
    }

    private function saveAddress(array $addressAr): false|int
    {
        [$latMin, $latMax, $lonMin, $lonMax] = array_map('floatval', $addressAr['boundingbox']);

        $polygonWkt = sprintf(
            'POLYGON((%f %f, %f %f, %f %f, %f %f, %f %f))',
            $lonMin, $latMin,
            $lonMin, $latMax,
            $lonMax, $latMax,
            $lonMax, $latMin,
            $lonMin, $latMin
        );

        try {
            $locAddress = ImageGeolocationAddress::create([
                'osm_id' => $addressAr['osm_id'],
                'osm_area' => DB::raw("ST_GeomFromText('$polygonWkt', 4326)"),
                'address' => $addressAr,
            ]);

            Log::info('Created new address', [
                'address_id' => $locAddress->id,
                'osm_id' => $addressAr['osm_id']
            ]);

            return $locAddress->id;
        } catch (\Exception $e) {
            Log::error('Failed to save geolocation address', [
                'error' => $e->getMessage(),
                'polygon' => $polygonWkt,
                'osm_id' => $addressAr['osm_id']
            ]);
            return false;
        }
    }
}
