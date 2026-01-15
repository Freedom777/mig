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
use MatanYadaev\EloquentSpatial\Objects\Point;

class GeolocationProcessJob extends BaseProcessJob
{
    public function handle() {
        // Используем более надежный lock на основе image_id
        $lockKey = 'geolocation-processing:' . $this->taskData['image_id'];
        $lock = Cache::lock($lockKey, 10); // 10 секунд

        try {
            $lock->block(10, function () {
                $this->processGeolocation();
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Could not acquire lock for geolocation processing: ' . $this->taskData['image_id']);
            // Можно сделать release для retry
            $this->release(30); // Повторить через 30 секунд
        } catch (\Exception $e) {
            Log::error('Geolocation processing failed: ' . $e->getMessage(), [
                'image_id' => $this->taskData['image_id']
            ]);
            throw $e;
        } finally {
            $this->complete();
        }
    }

    private function processGeolocation() {
        $metadata = json_decode($this->taskData['metadata'], true);

        if (!$metadata) {
            throw new \Exception('Invalid metadata JSON');
        }

        // Извлекаем координаты
        [$latitude, $longitude] = $this->extractCoordinates($metadata);

        $pointLatLon = new Point($latitude, $longitude);

        // Ищем существующую точку
        $point = ImageGeolocationPoint::where('coordinates', $pointLatLon)->first();

        if (!$point) {
            // Ищем адрес, чей bounding box содержит эту точку
            $addressId = ImageGeolocationAddress::whereContains('osm_area', $pointLatLon)->value('id');

            if (!$addressId) {
                $addressId = $this->getAddressId($latitude, $longitude);
                if (!$addressId) {
                    throw new \Exception('Failed to get address from Nominatim API');
                }
            }

            $point = ImageGeolocationPoint::create([
                'image_geolocation_address_id' => $addressId,
                'coordinates' => $pointLatLon,
            ]);

            Log::info('Created new geolocation point', [
                'point_id' => $point->id,
                'coordinates' => [$latitude, $longitude]
            ]);
        }

        // Обновляем изображение
        Image::where('id', $this->taskData['image_id'])
            ->update(['image_geolocation_point_id' => $point->id]);

        Log::info('Geolocation processed successfully for image ' . $this->taskData['image_id']);
    }

    /**
     * Извлекает координаты из метадаты
     */
    private function extractCoordinates(array $metadata): array {
        if (isset($metadata['GPSLatitude']) && isset($metadata['GPSLongitude'])) {
            return [
                (float) $metadata['GPSLatitude'],
                (float) $metadata['GPSLongitude']
            ];
        }

        if (isset($metadata['GPSPosition'])) {
            $parts = explode(' ', $metadata['GPSPosition']);
            if (count($parts) >= 2) {
                return [
                    (float) $parts[0],
                    (float) $parts[1]
                ];
            }
        }

        throw new \Exception('No valid GPS coordinates found in metadata');
    }

    private function getAddressId(float $latitude, float $longitude): false|int {
        $locUrl = Str::of(config('app.geolocation_api_url'))
            ->replace('{latitude}', $latitude)
            ->replace('{longitude}', $longitude)
            ->__toString();

        try {
            $response = Http::timeout(10)
                ->retry(3, 1000) // 3 попытки с задержкой 1 секунда
                ->withHeaders(['User-Agent' => 'MyGeocodingApp/1.0'])
                ->get($locUrl);

            if (!$response->ok()) {
                Log::error('Nominatim API request failed', [
                    'status' => $response->status(),
                    'url' => $locUrl
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

            // Проверяем, может адрес уже существует по osm_id
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

    private function saveAddress(array $addressAr): false|int {
        // boundingbox: [lat_min, lat_max, lon_min, lon_max]
        [$latMin, $latMax, $lonMin, $lonMax] = array_map('floatval', $addressAr['boundingbox']);

        // Построение полигона (WKT) в порядке lon lat (X Y)
        $polygonWkt = sprintf(
            'POLYGON((%f %f, %f %f, %f %f, %f %f, %f %f))',
            $lonMin, $latMin,  // нижний левый
            $lonMin, $latMax,  // верхний левый
            $lonMax, $latMax,  // верхний правый
            $lonMax, $latMin,  // нижний правый
            $lonMin, $latMin   // замыкаем
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


// https://nominatim.openstreetmap.org/reverse?format=json&lat=48.207177&lon=16.3619444&accept-language=ru
/*

{
  "place_id": 174327029,
  "licence": "Data © OpenStreetMap contributors, ODbL 1.0. http://osm.org/copyright",
  "osm_type": "way",
  "osm_id": 295108420,
  "lat": "48.2069523",
  "lon": "16.3619480",
  "class": "amenity",
  "type": "parking",
  "place_rank": 30,
  "importance": 0.000086942092011856,
  "addresstype": "amenity",
  "name": "",
  "display_name": "Ballhausplatz, Regierungsviertel, Innere Stadt, Wien, Вена, 1010, Австрия",
  "address": {
    "road": "Ballhausplatz",
    "neighbourhood": "Regierungsviertel",
    "suburb": "Innere Stadt",
    "city_district": "Wien",
    "city": "Вена",
    "ISO3166-2-lvl4": "AT-9",
    "postcode": "1010",
    "country": "Австрия",
    "country_code": "at"
  },
  "boundingbox": [
    "48.2063881",
    "48.2078931",
    "16.3610222",
    "16.3636037"
  ]
}

 */
