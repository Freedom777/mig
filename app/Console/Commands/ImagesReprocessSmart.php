<?php

namespace App\Console\Commands;

use App\Enums\ImageStatusEnum;
use App\Jobs\FaceProcessJob;
use App\Jobs\GeolocationProcessJob;
use App\Jobs\ImageProcessJob;
use App\Jobs\MetadataProcessJob;
use App\Jobs\ThumbnailProcessJob;
use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ImagesReprocessSmart extends Command
{
    protected $signature = 'images:reprocess:smart
                            {--batch-size=20 : Number of images per batch}
                            {--max-queue-size=50 : Maximum queue size before waiting}
                            {--check-interval=10 : Seconds between queue checks}
                            {--queue=faces : Which queue to process}
                            {--filter=faces-failed : Filter (faces-failed, no-debug, no-metadata, no-webp, no-hash, etc)}
                            {--max-batches= : Maximum number of batches (empty = all)}';

    protected $description = 'Smart reprocessing with queue monitoring';

    private int $processed = 0;
    private int $batches = 0;
    private ?string $rabbitHost = null;
    private ?int $rabbitPort = null;
    private ?string $rabbitUser = null;
    private ?string $rabbitPass = null;
    private ?string $rabbitVhost = null;

    public function handle(): int
    {
        $batchSize = (int)$this->option('batch-size');
        $maxQueueSize = (int)$this->option('max-queue-size');
        $checkInterval = (int)$this->option('check-interval');
        $queueName = $this->option('queue');
        $filter = $this->option('filter');
        $maxBatches = $this->option('max-batches') ? (int)$this->option('max-batches') : null;

        // Настройки RabbitMQ
        $this->rabbitHost = env('RABBITMQ_HOST', 'localhost');
        $this->rabbitPort = env('RABBITMQ_API_PORT', 15672);
        $this->rabbitUser = env('RABBITMQ_USER', 'guest');
        $this->rabbitPass = env('RABBITMQ_PASSWORD', 'guest');
        $this->rabbitVhost = env('RABBITMQ_VHOST', '/');

        $this->info("🚀 Smart reprocessing started");
        $this->line("  - Batch size: {$batchSize}");
        $this->line("  - Max queue size: {$maxQueueSize}");
        $this->line("  - Check interval: {$checkInterval}s");
        $this->line("  - Queue: {$queueName}");
        $this->line("  - Filter: {$filter}");
        $this->line("  - RabbitMQ: {$this->rabbitHost}:{$this->rabbitPort}");

        // Проверяем доступность RabbitMQ API
        if (!$this->testRabbitMQConnection()) {
            $this->error("❌ Cannot connect to RabbitMQ Management API");
            $this->line("Please check:");
            $this->line("  - RabbitMQ Management plugin is enabled");
            $this->line("  - RABBITMQ_API_PORT in .env (default: 15672)");
            $this->line("  - Credentials are correct");
            return CommandAlias::FAILURE;
        }

        // Получаем общее количество
        $total = $this->getImagesCount($filter);

        if ($total === 0) {
            $this->warn('No images found for processing');
            return CommandAlias::SUCCESS;
        }

        $this->info("📊 Total images to process: {$total}");
        $this->newLine();

        // Обрабатываем партиями
        while (true) {
            // Проверка лимита партий
            if ($maxBatches && $this->batches >= $maxBatches) {
                $this->info("✅ Reached max batches limit ({$maxBatches})");
                break;
            }

            // Проверяем сколько осталось
            $remaining = $this->getImagesCount($filter);

            if ($remaining === 0) {
                $this->info("✅ All images processed!");
                break;
            }

            // Проверяем размер очереди
            $queueSize = $this->getQueueSize($queueName);

            if ($queueSize === null) {
                $this->error("❌ Cannot get queue size, aborting");
                break;
            }

            $this->line("📦 Queue '{$queueName}' size: {$queueSize} | Remaining images: {$remaining}");

            // Если очередь слишком большая - ждём
            if ($queueSize >= $maxQueueSize) {
                $this->warn("⏳ Queue is full ({$queueSize} >= {$maxQueueSize}), waiting {$checkInterval}s...");
                sleep($checkInterval);
                continue;
            }

            // Отправляем новую партию
            $actualBatchSize = min($batchSize, $remaining, $maxQueueSize - $queueSize);

            $this->info('🔄 Processing batch #' . ++$this->batches . ' (' . $actualBatchSize . ' images)...');

            $this->processBatch($filter, $queueName, $actualBatchSize);

            $this->processed += $actualBatchSize;

            // Небольшая пауза перед следующей проверкой
            sleep(2);
        }

        $this->newLine();
        $this->info("🎉 Smart reprocessing completed!");
        $this->line("  - Batches processed: {$this->batches}");
        $this->line("  - Images queued: {$this->processed}");

        return CommandAlias::SUCCESS;
    }

    /**
     * Проверить подключение к RabbitMQ Management API
     */
    private function testRabbitMQConnection(): bool
    {
        try {
            $url = "http://{$this->rabbitHost}:{$this->rabbitPort}/api/overview";

            $response = Http::timeout(5)
                ->withBasicAuth($this->rabbitUser, $this->rabbitPass)
                ->get($url);

            return $response->successful();

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Получить размер очереди через RabbitMQ Management API
     */
    private function getQueueSize(string $queueName): ?int
    {
        try {
            $vhostEncoded = urlencode($this->rabbitVhost);
            $url = "http://{$this->rabbitHost}:{$this->rabbitPort}/api/queues/{$vhostEncoded}/{$queueName}";

            $response = Http::timeout(5)
                ->withBasicAuth($this->rabbitUser, $this->rabbitPass)
                ->get($url);

            if ($response->successful()) {
                $data = $response->json();
                return $data['messages'] ?? 0;
            }

            return null;

        } catch (\Exception $e) {
            $this->error('Error getting queue size: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Получить количество изображений для обработки
     */
    private function getImagesCount(string $filter): int
    {
        $query = Image::query();

        match($filter) {
            'faces-failed' => $query->where('faces_checked', 0),
            'no-debug' => $query->where('faces_checked', 1)->whereNull('debug_filename'),
            'no-metadata' => $query->whereNull('metadata'),
            'no-thumbnails' => $query->whereNull('thumbnail_path'),
            'no-webp' => $query->where(function ($q) {
                $q->where('filename', 'like', '%.jpg')
                  ->orWhere('filename', 'like', '%.jpeg');
            }),
            'no-hash' => $query->whereNull('hash'),
            'has-gps' => $query->whereNotNull('metadata')
                ->where(function ($q) {
                    $q->where(function ($subQ) {
                        $subQ->whereNotNull('metadata->GPSLatitude')
                             ->whereNotNull('metadata->GPSLongitude');
                    })->orWhereNotNull('metadata->GPSPosition');
                })
                ->whereNull('image_geolocation_point_id'),
            default => null
        };

        return $query->count();
    }

    /**
     * Обработать партию изображений
     */
    private function processBatch(string $filter, string $queueName, int $limit): void
    {
        $query = Image::query();

        match($filter) {
            'faces-failed' => $query->where('faces_checked', 0),
            'no-debug' => $query->where('faces_checked', 1)->whereNull('debug_filename'),
            'no-metadata' => $query->whereNull('metadata'),
            'no-thumbnails' => $query->whereNull('thumbnail_path'),
            'no-webp' => $query->where(function ($q) {
                $q->where('filename', 'like', '%.jpg')
                  ->orWhere('filename', 'like', '%.jpeg');
            }),
            'no-hash' => $query->whereNull('hash'),
            'has-gps' => $query->whereNotNull('metadata')
                ->where(function ($q) {
                    $q->where(function ($subQ) {
                        $subQ->whereNotNull('metadata->GPSLatitude')
                             ->whereNotNull('metadata->GPSLongitude');
                    })->orWhereNotNull('metadata->GPSPosition');
                })
                ->whereNull('image_geolocation_point_id'),
            default => null
        };

        $images = $query->limit($limit)->orderBy('id')->get();

        foreach ($images as $image) {
            $this->dispatchJob($image, $queueName);

            // Сбрасываем статус recheck → process
            if ($image->status === ImageStatusEnum::Recheck->value) {
                $image->update(['status' => ImageStatusEnum::Process->value]);
            }
        }
    }

    /**
     * Отправить джобу в очередь
     */
    private function dispatchJob(Image $image, string $queueName): void
    {
        $jobData = ['image_id' => $image->id];

        match($queueName) {
            config('queue.name.images') => ImageProcessJob::dispatch($jobData)->onQueue(config('queue.name.images')),
            config('queue.name.faces') => FaceProcessJob::dispatch($jobData)->onQueue(config('queue.name.faces')),
            config('queue.name.metadatas') => MetadataProcessJob::dispatch($jobData)->onQueue(config('queue.name.metadatas')),
            config('queue.name.thumbnails') => ThumbnailProcessJob::dispatch($jobData)->onQueue(config('queue.name.thumbnails')),
            config('queue.name.geolocations') => GeolocationProcessJob::dispatch($jobData)->onQueue(config('queue.name.geolocations')),
            default => null
        };
    }
}
