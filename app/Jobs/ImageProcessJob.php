<?php

namespace App\Jobs;

use App\Models\Image;
use Illuminate\Support\Facades\Log;

class ImageProcessJob extends BaseProcessJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Image::updateInsert($this->taskData);
        } catch (\Exception $e) {
            Log::error('ImageProcessJob failed: ' . $e->getMessage());
        } finally {
            $this->complete();
        }
    }
}
