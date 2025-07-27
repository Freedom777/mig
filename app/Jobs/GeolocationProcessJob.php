<?php

namespace App\Jobs;

use App\Models\Image;
use App\Models\ImageGeolocationAddress;
use App\Models\ImageGeolocationPoint;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MatanYadaev\EloquentSpatial\Objects\Point;

class GeolocationProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $taskData;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $taskData)
    {
        $this->taskData = $taskData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $lock = Cache::lock('job-lock:' . $this->job->getJobId(), 2);

        $lock->block(2, function () {
            try {
                $metadata = json_decode($this->taskData['metadata'], true);

                if (isset($metadata['GPSLatitude']) && isset($metadata['GPSLongitude'])) {
                    $latitude = $metadata['GPSLatitude'];
                    $longitude = $metadata['GPSLongitude'];
                } elseif (isset($metadata['GPSPosition'])) {
                    [$latitude, $longitude] = explode(' ', $metadata['GPSPosition']);
                } else {
                    throw new \Exception('Failed to process GPS location from metadata: ' . json_encode($metadata));
                }

                $pointLatLon = new Point($latitude, $longitude);
                $point = ImageGeolocationPoint::where('coordinates', $pointLatLon)->first();
                if (!$point) {
                    $addressId = ImageGeolocationAddress::whereContains('osm_area', $point)->value('id');
                    if (!$addressId) {
                        $addressId = $this->getAddressId($latitude, $longitude);
                        if (!$addressId) {
                            throw new \Exception('Get address from URL process failed.');
                        }
                    }

                    $point = new ImageGeolocationPoint();
                    $point->image_geolocation_address_id = $addressId;
                    $point->coordinates = $pointLatLon;
                    $point->save();
                }
                Image::where('id', $this->taskData['image_id'])->update(['image_geolocation_point_id' => $point->id]);
            } catch (\Exception $e) {
                throw new \Exception('Failed to process geolocation: ' . $e->getMessage());
            }
        });
    }

    private function getAddressId(float $latitude, float $longitude) : false|int {
        $locUrl = Str::of(env('GEOLOCATION_URL'))
            ->replace('{latitude}', $latitude)
            ->replace('{longitude}', $longitude)
            ->__toString();

        $response = Http::withHeaders([
            'User-Agent' => 'MyGeocodingApp/1.0'
        ])->get($locUrl);

        if (!$response->ok()) {
            return false;
        }

        $addressAr = $response->json();
        if (!is_array($addressAr)) {
            Log::error('Invalid geolocation response: ' . $response->body());
            return false;
        }

        $locAddress = new ImageGeolocationAddress();
        $locAddress->osm_id = $addressAr['osm_id'];
        $locAddress->osm_area = $addressAr['boundingbox'];
        $locAddress->address = $addressAr;
        $locAddress->save();

        return $locAddress->id;
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
