<?php

namespace App\Traits;

use App\Models\Queue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

trait QueueAbleTrait
{
    public static function pushToQueue(string $className, string $queueName, array $data)
    {
        $queueKey = self::generateQueueKey($className, $data);
        $objectName = explode(' ', Str::headline($className))[0];

        try {
            Queue::create(['queue_key' => $queueKey]);
            $className::dispatch($data)->onQueue($queueName);

            return response()->json([
                'status' => 'success',
                'message' => $objectName . ' added to processing queue',
                'data' => $data
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'exists',
                'message' => $objectName . ' already exists in processing queue',
                'data' => $data
            ]);
        }
    }

    public static function removeFromQueue(string $className, array $data): void
    {
        $queueKey = self::generateQueueKey($className, $data);
        Queue::byKey($queueKey)->delete();
    }

    public static function existsInQueue(string $className, array $data): bool
    {
        $queueKey = self::generateQueueKey($className, $data);
        return Queue::byKey($queueKey)->exists();
    }

    /**
     * Генерация ключа очереди
     * Вынесено в отдельный метод для консистентности
     */
    protected static function generateQueueKey(string $className, array $data): string
    {
        return md5(json_encode(['class' => $className] + $data));
    }
}
