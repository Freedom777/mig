<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GeolocationProcessJob;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApiGeolocationProcessController extends Controller
{
    public function process(Request $request)
    {
        try {
            $data = $request->validate([
                'image_id' => 'required|integer|min:1',
                'metadata' => 'required|string',
            ], [
                'image_id.required' => 'Image ID is required',
                'image_id.integer' => 'Image ID must be an integer',
                'image_id.min' => 'Image ID must be at least 1 byte',
                'metadata.required' => 'Metadata is required',
            ]);

            GeolocationProcessJob::dispatch($data)->onQueue(env('QUEUE_GEOLOCATIONS'));

            return response()->json([
                'status' => 'queued',
                'message' => 'Geolocation added to processing queue',
                'data' => $data // Опционально - возвращаем принятые данные
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
