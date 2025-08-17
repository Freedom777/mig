<?php

namespace App\Jobs;

use App\Models\Face;
use App\Models\Image;
use App\Services\ImagePathService;
use App\Traits\QueueAbleTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class FaceProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueAbleTrait;

    protected $taskData;

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
        try {
            $image = Image::find($this->taskData['image_id']);
            $imagePath = ImagePathService::getImagePath($image);

            // Отправка изображения на Python API
            $response = Http::attach(
                'image', file_get_contents($imagePath), $image->filename
            )->post(config('app.face_api_url') . '/encode', [
                'original_disk' => $image->disk,
                'original_path' => $imagePath,
                'image_debug_subdir' => ImagePathService::getImageDebugSubdir()
            ]);

            if (!$response->successful()) {
                throw new \Exception('Face API /encode failed: ' . $response->body());
            }

            $newEncodings = $response->json()['encodings'] ?? [];
            $faces = Face::query()
                ->whereNotNull('encoding')
                ->get(['id', 'encoding', 'parent_id']); // сразу тащим parent_id

            foreach ($newEncodings as $idx => $newEncoding) {
                $newFace = new Face();
                $newFace->encoding = $newEncoding;

                if ($faces->isNotEmpty()) {
                    $knownEncodings = $faces->pluck('encoding')->toArray();

                    $compareResponse = Http::post(config('app.face_api_url') . '/compare', [
                        'encoding'   => $newEncoding,
                        'candidates' => $knownEncodings,
                    ]);

                    if ($compareResponse->successful()) {
                        $distances = $compareResponse->json()['distances'] ?? [];

                        if ($distances) {
                            $minValue = min($distances);
                            $minIndex = array_search($minValue, $distances);
                            if ($minValue < 0.6) {
                                $matchedFace = $faces[$minIndex];
                                $newFace->parent_id = $matchedFace->parent_id ?? $matchedFace->id;
                            }
                        }
                    } else {
                        Log::warning('Face API /compare failed: ' . $compareResponse->body());
                    }
                }

                $newFace->image_id = $image->id;
                $newFace->face_index = $idx;
                $newFace->save();

                // Добавляем нового в коллекцию для следующих сравнений
                $faces->push($newFace->only(['id', 'encoding', 'parent_id']));
            }

            $image->debug_filename = is_null($debugPath = $response->json()['debug_image_path'])
                ? null
                : basename($debugPath);

            $image->faces_checked = 1;
            $image->save();
        } catch (\Exception $e) {
            Log::error('Failed to process face: ' . $e->getMessage());
        } finally {
            $this->removeFromQueue(self::class, $this->taskData);
        }
    }
}
