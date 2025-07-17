<?php

namespace App\Jobs;

use App\Models\Face;
use App\Models\Image;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            $imagePath = Storage::disk($image->disk)->path($image->path . '/' . $image->filename);

            // Отправка изображения на Python API
            $response = Http::attach(
                'image', file_get_contents($imagePath), $image->filename
            )->post(config('app.face_api_url') . '/encode', [
                'original_path' => $imagePath
            ]);

            if (!$response->successful()) {
                throw new \Exception('Face API /encode failed: ' . $response->body());
            }

            $newEncodings = $response->json()['encodings'] ?? [];
            $faces = Face::all();

            foreach ($newEncodings as $idx => $newEncoding) {
                $newFace = new Face();
                $newFace->encoding = $newEncoding;

                if ($faces->count()) {
                    $knownEncodings = $faces->pluck('encoding')->toArray();
                    $compareResponse = Http::post(config('app.face_api_url') . '/compare', [
                        'encoding' => $newEncoding,
                        'candidates' => $knownEncodings,
                    ]);

                    if ($compareResponse->successful()) {
                        $distances = $compareResponse->json()['distances'] ?? [];

                        foreach ($distances as $index => $distance) {
                            if ($distance < 0.6) {
                                $matchedFace = $faces[$index];
                                $newFace->parent_id = $matchedFace->parent_id ?? $matchedFace->id;
                                break;
                            }
                        }
                    } else {
                        Log::warning('Face API /compare failed: ' . $compareResponse->body());
                    }
                }

                $newFace->image_id = $image->id;
                $newFace->idx = $idx;
                $newFace->save();
                $faces[] = $newFace;
            }

            // $image->faces()->sync($facesAttach);
            $image->debug_filename = basename($response->json()['debug_image_path']);
            $image->faces_checked = 1;
            $image->save();
        } catch (\Exception $e) {
            Log::error('Failed to process face: ' . $e->getMessage());
        }
    }
}
