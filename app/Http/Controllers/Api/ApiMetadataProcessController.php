<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\MetadataProcessJob;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApiMetadataProcessController extends Controller
{
    public function process(Request $request)
    {
        try {
            $data = $request->validate([
                'image_id' => 'required|integer|min:1',
                'source_disk' => 'required|string',
                'source_path' => 'required|string',
                'source_filename' => 'required|string',
            ], [
                'image_id.required' => 'Image ID is required',
                'image_id.integer' => 'Image ID must be an integer',
                'image_id.min' => 'Image ID must be at least 1 byte',
                'source_disk.required' => 'Source disk is required',
                'source_path.required' => 'Source path is required',
                'source_filename.required' => 'Source filename is required',
            ]);

            MetadataProcessJob::dispatch($data)->onQueue(env('QUEUE_METADATAS'));

            return response()->json([
                'status' => 'queued',
                'message' => 'Metadata added to processing queue',
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
