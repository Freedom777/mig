<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApiClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('app.api_url'), '/');
    }

    /**
     * @throws \Exception
     */
    public function imageProcess(array $data)
    {
        return $this->post('/api/image/process', $data);
    }

    /**
     * @throws \Exception
     */
    public function thumbnailProcess(array $data)
    {
        return $this->post('/api/thumbnail/process', $data);
    }

    /**
     * @throws \Exception
     */
    public function metadataProcess(array $data)
    {
        return $this->post('/api/metadata/process', $data);
    }

    /**
     * @throws \Exception
     */
    public function geolocationProcess(array $data)
    {
        return $this->post('/api/geolocation/process', $data);
    }

    /**
     * @throws \Exception
     */
    public function faceProcess(array $data)
    {
        return $this->post('/api/face/process', $data);
    }

    protected function post(string $uri, array $data)
    {
        $response = Http::withHeaders([
            'User-Agent' => 'MyApp/1.0'
        ])->post($this->baseUrl . $uri, $data);

        /*if (!$response->ok()) {
            throw new \Exception('API request to ' . $uri . ' failed with status ' . $response->status());
        }*/

        return $response;
    }
}
