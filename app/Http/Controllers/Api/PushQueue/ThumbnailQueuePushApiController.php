<?php

namespace App\Http\Controllers\Api\PushQueue;

use App\Http\Controllers\Controller;
use App\Jobs\ThumbnailProcessJob;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Traits\QueueAbleTrait;

class ThumbnailQueuePushApiController extends Controller
{
    use QueueAbleTrait;

    public function process(Request $request)
    {
        try {
            $data = $request->validate([
                'disk' => 'required|string',
                'source_path' => 'required|string',
                'source_filename' => 'required|string',
                'thumbnail_path' => 'required|string',
                'thumbnail_filename' => 'required|string',
                'thumbnail_method' => 'required|string',
                'thumbnail_width' => 'required|integer|min:1',
                'thumbnail_height' => 'required|integer|min:1',
            ], [
                'disk.required' => 'Disk is required',
                'source_path.required' => 'Source path is required',
                'source_filename.required' => 'Source filename is required',
                'thumbnail_path.required' => 'Thumbnail path is required',
                'thumbnail_filename.required' => 'Thumbnail filename is required',
                'thumbnail_method.required' => 'Thumbnail method is required',
                'thumbnail_width.required' => 'Thumbnail width is required',
                'thumbnail_width.integer' => 'Thumbnail width must be an integer',
                'thumbnail_width.min' => 'Thumbnail width must be at least 1 byte',
                'thumbnail_height.required' => 'Thumbnail height is required',
                'thumbnail_height.integer' => 'Thumbnail height must be an integer',
                'thumbnail_height.min' => 'Thumbnail height must be at least 1 byte',
            ]);

            return $this->pushToQueue(ThumbnailProcessJob::class, config('queue.name.thumbnails'), $data);
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
