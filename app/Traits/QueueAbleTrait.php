<?php

namespace App\Traits;

use App\Models\Queue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

trait QueueAbleTrait {
    public function pushToQueue($className, $queueName, $data) {
        $queueKey = md5(json_encode(['class' => $className]+$data));
        $objectName = explode(' ', Str::headline($className))[0]; // ImageProcessJob => Image

        try {
            Queue::create(['queue_key' => $queueKey]);
            $className::dispatch($data)->onQueue($queueName);
            return response()->json([
                'status' => 'success',
                'message' => $objectName . ' added to processing queue',
                'data' => $data // Опционально - возвращаем принятые данные
            ]);
        } catch (QueryException $e) {
            // задача уже в логе — пропускаем
            return response()->json([
                'status' => 'exists',
                'message' => $objectName . ' already exists in processing queue',
                'data' => $data // Опционально - возвращаем принятые данные
            ]);
        }
    }

    public function removeFromQueue($className, $data) {
        $queueKey = md5(json_encode(['class' => $className]+$data));
        $queue = Queue::where('queue_key', hex2bin($queueKey))->first();
        if ($queue) {
            $queue->delete();
        }
    }
}
