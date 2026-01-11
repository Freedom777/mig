<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GeolocationProcessJob;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Traits\QueueAbleTrait;

class ApiGeolocationProcessController extends Controller
{
    use QueueAbleTrait;

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

            return self::pushToQueue(GeolocationProcessJob::class, config('queue.name.geolocations'), $data);
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
