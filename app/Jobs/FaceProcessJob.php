<?php

namespace App\Jobs;

use App\Models\Face;
use App\Models\Image;
use App\Services\ImagePathService;
use App\Traits\QueueAbleTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

            // 1. Ошибки сети или 500 от API
            if (!$response->successful()) {
                $image->last_error = 'HTTP ' . $response->status() . ': ' . Str::limit($response->body(), 200);
                $image->save();
                throw new \Exception('Face API /encode failed: ' . $response->body());
            }

            $responseData = $response->json();
            // 2. Ошибки бизнес-логики (CUDA OOM, нет лиц и т.д.)
            if (isset($responseData['error'])) {
                $image->last_error = $responseData['error'];
            } else {
                $image->last_error = null;
                $newEncodings = $responseData['encodings'] ?? [];
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
                                if ($minValue < 0.6) {
                                    $minIndex = array_search($minValue, $distances);
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

                $debugPath = $responseData['debug_image_path'] ?? null;
                $image->debug_filename = $debugPath ? basename($debugPath) : null;

                $image->faces_checked = 1;
            }
            $image->save();

        } catch (\Exception $e) {
            Log::error('Failed to process face: ' . $e->getMessage());
        } finally {
            $this->removeFromQueue(self::class, $this->taskData);
        }
    }
}
