<?php

namespace App\Jobs;

use App\Models\Image;
use App\Traits\QueueAbleTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class MetadataProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueAbleTrait;

    protected array $taskData;

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
        $disk = Storage::disk($this->taskData['source_disk']);
        $sourcePath = $disk->path($this->taskData['source_path'] . '/' . $this->taskData['source_filename']);

        try {
            $process = new Process(['exiftool', '-json', '-n', $sourcePath]);
            $process->run();

            $output = $process->getOutput();

            if (!$process->isSuccessful()) {
                Log::error('Extraction metadata process failed: (' . $sourcePath . '): ' . $process->getErrorOutput());
            } else {
                $data = json_decode($output, true);
                if (is_array($data) && isset($data[0])) {
                    $metadata = $data[0];
                } else {
                    $metadata = NULL; // или null, если ничего нет
                }
                Image::where('id', $this->taskData['image_id'])->update(['metadata' => $metadata]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process metadata from image ' . $sourcePath . ': ' . $e->getMessage());
        } finally {
            $this->removeFromQueue(self::class, $this->taskData);
        }
    }
}
