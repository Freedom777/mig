<?php

namespace App\Services;

use App\Contracts\ImagePathServiceInterface;
use App\Contracts\ImageQueueDispatcherInterface;
use App\Jobs\BaseProcessJob;
use App\Jobs\ConvertToWebpJob;
use App\Jobs\FaceProcessJob;
use App\Jobs\GeolocationProcessJob;
use App\Jobs\ImageProcessJob;
use App\Jobs\MetadataProcessJob;
use App\Jobs\ThumbnailProcessJob;
use App\Models\Image;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ImageQueueDispatcher implements ImageQueueDispatcherInterface
{
    protected const VALID_MODES = ['queue', 'sync', 'disabled'];

    protected ?string $modeOverride = null;
    protected ?bool $dryRunOverride = null;
    protected ?bool $debugOverride = null;

    public function __construct(
        protected ImagePathServiceInterface $pathService
    ) {}

    // =========================================================================
    // Configuration Getters/Setters
    // =========================================================================

    public function getMode(): string
    {
        return $this->modeOverride ?? config('image.processing.mode', 'queue');
    }

    public function setMode(string $mode): self
    {
        if (!in_array($mode, self::VALID_MODES)) {
            throw new \InvalidArgumentException(
                "Invalid processing mode: {$mode}. Valid: " . implode(', ', self::VALID_MODES)
            );
        }
        $this->modeOverride = $mode;
        return $this;
    }

    public function isDryRun(): bool
    {
        return $this->dryRunOverride ?? config('image.processing.dry_run', false);
    }

    public function setDryRun(bool $dryRun): self
    {
        $this->dryRunOverride = $dryRun;
        return $this;
    }

    public function isDebug(): bool
    {
        return $this->debugOverride ?? config('image.processing.debug', false);
    }

    public function setDebug(bool $debug): self
    {
        $this->debugOverride = $debug;
        return $this;
    }

    // =========================================================================
    // Main Dispatch Methods
    // =========================================================================

    public function dispatchAll(Image $image): array
    {
        $mode = $this->getMode();
        $dryRun = $this->isDryRun();
        $debug = $this->isDebug();

        if ($debug) {
            Log::debug('ImageQueueDispatcher::dispatchAll started', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'mode' => $mode,
                'dry_run' => $dryRun,
            ]);
        }

        if ($mode === 'disabled') {
            if ($debug) {
                Log::debug('Processing disabled, skipping all jobs', ['image_id' => $image->id]);
            }
            return [
                'image' => 'skipped',
                'thumbnail' => 'skipped',
                'metadata' => 'skipped',
                'face' => 'skipped',
                'webp' => 'skipped',
            ];
        }

        $data = ['image_id' => $image->id];

        if ($dryRun) {
            Log::info('[DRY-RUN] Would chain jobs for image', [
                'image_id' => $image->id,
                'mode' => $mode,
                'jobs' => ['image', 'thumbnail', 'metadata', 'face', 'webp']
            ]);
            return [
                'image' => 'dry-run',
                'thumbnail' => 'dry-run',
                'metadata' => 'dry-run',
                'face' => 'dry-run',
                'webp' => 'dry-run',
            ];
        }

        // Используем Bus::chain для последовательного выполнения
        // ConvertToWebpJob ПОСЛЕДНЯЯ - выполнится только после всех остальных
        try {
            if ($mode === 'sync') {
                // Sync режим - выполняем последовательно
                $statuses = [];
                $statuses['image'] = $this->executeSync(ImageProcessJob::class, $data, 'Image', $image->id, $debug);
                $statuses['thumbnail'] = $this->executeSync(ThumbnailProcessJob::class, $data, 'Thumbnail', $image->id, $debug);
                $statuses['metadata'] = $this->executeSync(MetadataProcessJob::class, $data, 'Metadata', $image->id, $debug);
                $statuses['face'] = $this->executeSync(FaceProcessJob::class, $data, 'Face', $image->id, $debug);
                $statuses['webp'] = $this->executeSync(ConvertToWebpJob::class, $data, 'WebpConversion', $image->id, $debug);
            } else {
                // Queue режим - используем Bus::chain()
                Bus::chain([
                    new ImageProcessJob($data),
                    new ThumbnailProcessJob($data),
                    new MetadataProcessJob($data),
                    new FaceProcessJob($data),
                    new ConvertToWebpJob($data), // ПОСЛЕДНЯЯ!
                ])->dispatch();

                $statuses = [
                    'image' => 'chained',
                    'thumbnail' => 'chained',
                    'metadata' => 'chained',
                    'face' => 'chained',
                    'webp' => 'chained',
                ];

                Log::info('Jobs chained successfully', [
                    'image_id' => $image->id,
                    'jobs_count' => 5
                ]);
            }

            return $statuses;

        } catch (\Exception $e) {
            Log::error('Failed to chain jobs', [
                'image_id' => $image->id,
                'error' => $e->getMessage()
            ]);
            return [
                'image' => 'error',
                'thumbnail' => 'error',
                'metadata' => 'error',
                'face' => 'error',
                'webp' => 'error',
            ];
        }
    }

    public function dispatchImageProcess(Image $image): string
    {
        return $this->dispatch(
            jobClass: ImageProcessJob::class,
            queue: config('queue.name.images'),
            imageId: $image->id,
            jobName: 'Image'
        );
    }

    public function dispatchThumbnail(Image $image): string
    {
        return $this->dispatch(
            jobClass: ThumbnailProcessJob::class,
            queue: config('queue.name.thumbnails'),
            imageId: $image->id,
            jobName: 'Thumbnail'
        );
    }

    public function dispatchMetadata(Image $image): string
    {
        return $this->dispatch(
            jobClass: MetadataProcessJob::class,
            queue: config('queue.name.metadatas'),
            imageId: $image->id,
            jobName: 'Metadata'
        );
    }

    public function dispatchGeolocation(Image $image): string
    {
        return $this->dispatch(
            jobClass: GeolocationProcessJob::class,
            queue: config('queue.name.geolocations'),
            imageId: $image->id,
            jobName: 'Geolocation'
        );
    }

    public function dispatchFace(Image $image): string
    {
        return $this->dispatch(
            jobClass: FaceProcessJob::class,
            queue: config('queue.name.faces'),
            imageId: $image->id,
            jobName: 'Face'
        );
    }

    public function dispatchWebpConversion(Image $image): string
    {
        return $this->dispatch(
            jobClass: ConvertToWebpJob::class,
            queue: config('queue.name.images'), // Та же очередь что и основная обработка
            imageId: $image->id,
            jobName: 'WebpConversion'
        );
    }

    // =========================================================================
    // Core Dispatch Logic
    // =========================================================================

    protected function dispatch(string $jobClass, string $queue, int $imageId, string $jobName): string
    {
        $mode = $this->getMode();
        $dryRun = $this->isDryRun();
        $debug = $this->isDebug();

        $data = ['image_id' => $imageId];

        if ($debug) {
            Log::debug("{$jobName} job dispatch", [
                'job_class' => $jobClass,
                'queue' => $queue,
                'mode' => $mode,
                'dry_run' => $dryRun,
                'data' => $data,
            ]);
        }

        if ($mode === 'disabled') {
            $this->logStatus($jobName, $imageId, 'skipped', 'disabled');
            return 'skipped';
        }

        if ($dryRun) {
            $action = $mode === 'sync' ? 'execute' : 'queue';
            Log::info("[DRY-RUN] Would {$action} {$jobName} job", [
                'job_class' => $jobClass,
                'queue' => $queue,
                'image_id' => $imageId,
            ]);
            $this->logStatus($jobName, $imageId, 'dry-run', $action);
            return 'dry-run';
        }

        if ($mode === 'sync') {
            return $this->executeSync($jobClass, $data, $jobName, $imageId, $debug);
        }

        return $this->executeQueue($jobClass, $queue, $data, $jobName, $imageId, $debug);
    }

    protected function executeSync(string $jobClass, array $data, string $jobName, int $imageId, bool $debug): string
    {
        $startTime = $debug ? microtime(true) : null;

        try {
            $job = new $jobClass($data);
            app()->call([$job, 'handle']);

            if ($debug && $startTime) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                Log::debug("{$jobName} job completed (sync)", [
                    'image_id' => $imageId,
                    'duration_ms' => $duration,
                ]);
            }

            $this->logStatus($jobName, $imageId, 'completed', 'sync');
            return 'completed';

        } catch (\Exception $e) {
            Log::error("{$jobName} job failed (sync)", [
                'image_id' => $imageId,
                'error' => $e->getMessage(),
                'trace' => $debug ? $e->getTraceAsString() : null,
            ]);
            return 'error';
        }
    }

    protected function executeQueue(string $jobClass, string $queue, array $data, string $jobName, int $imageId, bool $debug): string
    {
        try {
            $response = BaseProcessJob::pushToQueue($jobClass, $queue, $data);
            $status = $response->getData()->status;

            if ($debug) {
                Log::debug("{$jobName} job queued", [
                    'image_id' => $imageId,
                    'queue' => $queue,
                    'status' => $status,
                ]);
            }

            $this->logStatus($jobName, $imageId, $status, 'queue');
            return $status;

        } catch (\Exception $e) {
            Log::error("{$jobName} job failed to queue", [
                'image_id' => $imageId,
                'error' => $e->getMessage(),
                'trace' => $debug ? $e->getTraceAsString() : null,
            ]);
            return 'error';
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function logStatus(string $jobName, int $imageId, string $status, string $context): void
    {
        $messages = [
            'success' => "{$jobName} job queued",
            'exists' => "{$jobName} job already in queue",
            'completed' => "{$jobName} job completed ({$context})",
            'dry-run' => "{$jobName} job skipped (dry-run, would {$context})",
            'skipped' => "{$jobName} job skipped ({$context})",
            'error' => "{$jobName} job failed",
        ];

        $message = $messages[$status] ?? "{$jobName} job: {$status}";

        Log::info($message, ['image_id' => $imageId]);
    }
}
