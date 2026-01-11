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

    public function __call($method, $parameters) {
        if (!str_ends_with($method, 'Process')) {
            throw new \BadMethodCallException('Undefined method: ' . $method);
        }

        if (!isset($parameters[0]) || !is_array($parameters[0])) {
            throw new \InvalidArgumentException(
                sprintf('Method %s expects array as first argument', $method)
            );
        }

        $resource = substr($method, 0, -7);
        $apiCall = '/api/' . $resource . '/process';

        return $this->post($apiCall, $parameters[0]);
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
