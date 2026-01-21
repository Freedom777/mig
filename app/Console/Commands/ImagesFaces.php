<?php

namespace App\Console\Commands;

use App\Jobs\FaceProcessJob;
use App\Models\Image;
use App\Traits\QueueAbleTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImagesFaces extends Command
{
    use QueueAbleTrait;

    protected $signature = 'images:faces';

    protected $description = 'Put all faces, which are not processed to queue.';

    public function handle() {
        try {
            Image::query()
                ->select('id')
                ->where('faces_checked', 0)
                ->chunkById(1000, function ($images) {
                    foreach ($images as $image) {
                        $response = self::pushToQueue(FaceProcessJob::class, config('queue.name.faces'), [
                            'image_id' => $image->id,
                        ]);

                        $responseData = $response->getData();

                        if ($responseData->status === 'success') {
                            Log::info('Face job queued', ['image_id' => $image->id]);
                        } elseif ($responseData->status === 'exists') {
                            Log::info('Face job already in queue', ['image_id' => $image->id]);
                        }
                        /*dispatch(new FaceProcessJob([
                            'image_id' => $image->id,
                        ]))->onQueue(config('queue.name.faces'));*/
                    }
                });
        } catch (\Exception $e) {
            $this->error('Failed to send to API object Faces: ' . $e->getMessage());
        }
    }
}
