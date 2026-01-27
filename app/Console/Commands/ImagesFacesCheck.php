<?php

namespace App\Console\Commands;

use App\Jobs\FaceProcessJob;
use App\Models\Face;
use App\Models\Image;
use App\Services\ImagePathService;
use App\Traits\QueueAbleTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImagesFacesCheck extends Command
{
    use QueueAbleTrait;

    protected $signature = 'images:faces:check';

    protected $description = 'Take all images, which are have face_checked true or have recheck status and push them to queue.';

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

    private function extractFaces(int $imageId)
    {
        try {
            $response = self::pushToQueue(FaceProcessJob::class, config('queue.name.faces'), [
                'image_id' => $imageId,
            ]);

            $responseData = $response->getData();

            if ($responseData->status === 'success') {
                Log::info('Face job queued', ['image_id' => $imageId]);
            } elseif ($responseData->status === 'exists') {
                Log::info('Face job already in queue', ['image_id' => $imageId]);
            }
        } catch (\Exception $e) {
            $this->error('Failed to send to API (image ID: ' . $imageId . '): ' . $e->getMessage());
        }
    }
}
