<?php

namespace App\Console\Commands;

use App\Models\Face;
use App\Models\Image;
use App\Services\ImagePathService;
use Illuminate\Console\Command;
use App\Services\ApiClient;

class ImagesFacesCheck extends Command
{
    protected $signature = 'images:faces:check';
    protected ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        parent::__construct();
        $this->apiClient = $apiClient;
    }

    public function handle()
    {
        $images = Image::where('faces_checked', 1)->orWhere('status', Image::STATUS_RECHECK)->get();

        foreach ($images as $image) {
            $debugImagePath = ImagePathService::getDebugImagePath($image);
            if ($image->status == Image::STATUS_RECHECK || !is_file($debugImagePath)) {
                $faces = $image->faces;
                foreach ($faces as $face) {
                    try {
                        // Check for parent_id faces not equal to deleting face id
                        // Not checking for equal image_id because same people on the photo is not possible ;)
                        $checkFaces = $face->children;
                        if ($checkFaces->count()) {
                            // Get first face from the collection and remove it from collection
                            $parentFace = $checkFaces->shift();
                            $parentFace->parent_id = null;
                            $parentFace->save();

                            $parentFace->children->each(function ($child) use ($parentFace) {
                                $child->parent_id = $parentFace->id;
                                $child->save();
                            });
                        }
                    } catch (\Exception $e) {
                        $this->error('Failed to operate with face children (face ID: ' . $face->id . '): ' . $e->getMessage());
                    }
                }
                Face::where('image_id', $image->id)->forceDelete();

                if ($image->status == Image::STATUS_RECHECK && file_exists($debugImagePath)) {
                    unlink($debugImagePath);
                }

                $image->faces_checked = 0;
                $image->debug_filename = null;
                $image->status = Image::STATUS_PROCESS;
                $image->save();
                $this->extractFaces($image->id);
            }
        }
    }

    private function extractFaces(int $id)
    {
        try {
            $requestData = [
                'image_id' => $id,
            ];
            $response = $this->apiClient->faceProcess($requestData);

            if ($response->successful()) {
                $this->info('Task queued, image ID: ' . $id);
            } else {
                $this->error('API error (image ID: ' . $id . '): ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Failed to send to API (image ID: ' . $id . '): ' . $e->getMessage());
        }
    }
}
