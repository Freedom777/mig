<?php

namespace App\Jobs;

use App\Traits\QueueAbleTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class BaseProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, QueueAbleTrait;

    protected array $taskData;

    public function __construct(array $taskData) {
        if (!isset($taskData['image_id'])) {
            throw new \InvalidArgumentException('taskData must contain image_id');
        }

        $this->taskData = $taskData;
    }

    protected function complete()
    {
        self::removeFromQueue(static::class, $this->taskData);
    }
}
