<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImageProcessJob;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Traits\QueueAbleTrait;

class ApiImageProcessController extends Controller
{
    use QueueAbleTrait;

    public function process(Request $request)
    {
        try {
            $data = $request->validate([
                'source_disk' => 'required|string',
                'source_path' => 'required|string',
                'source_filename' => 'required|string',
                'width' => 'required|integer|min:1',
                'height' => 'required|integer|min:1',
                'size' => 'required|integer|min:1',
                'hash' => ['required', 'string', 'size:32', 'regex:/^[a-f0-9]{32}$/i'],
                'created_at_file' => 'required|date',
                'updated_at_file' => 'required|date',
                'parent_id' => 'nullable|integer|exists:images,id',
            ], [
                'source_disk.required' => 'Source disk is required',
                'source_path.required' => 'Source path is required',
                'source_filename.required' => 'Source filename is required',
                'width.required' => 'Width is required',
                'width.integer' => 'Width must be an integer',
                'width.min' => 'Width must be at least 1 byte',
                'height.required' => 'Height is required',
                'height.integer' => 'Height must be an integer',
                'height.min' => 'Height must be at least 1 byte',
                'size.required' => 'File size is required',
                'size.integer' => 'File size must be an integer',
                'size.min' => 'File size must be at least 1 byte',
                'hash.required' => 'Hash is required',
                'hash.size' => 'Hash must be exactly 32 characters.',
                'hash.regex' => 'Hash format is invalid. Must be a 32-character hexadecimal string.',
                'created_at_file.required' => 'Creation date is required',
                'created_at_file.date' => 'Invalid creation date format',
                'updated_at_file.required' => 'Modification date is required',
                'updated_at_file.date' => 'Invalid modification date format',
                'parent_id.integer' => 'Duplicate with ID must be an integer',
                'parent_id.exists' => 'Duplicate with ID provided not found in database.',
            ]);

            return self::pushToQueue(ImageProcessJob::class, config('queue.name.images'), $data);
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
