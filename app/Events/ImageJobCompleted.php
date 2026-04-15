<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImageJobCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * ID изображения
     */
    public int $imageId;

    /**
     * Тип завершённой job
     */
    public string $jobType;

    /**
     * Create a new event instance.
     */
    public function __construct(int $imageId, string $jobType)
    {
        $this->imageId = $imageId;
        $this->jobType = $jobType;
    }
}
