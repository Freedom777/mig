<?php

namespace App\Services;

use App\Contracts\ImagePathServiceInterface;
use App\Contracts\ImageQueueDispatcherInterface;
use App\Jobs\BaseProcessJob;
use App\Jobs\FaceProcessJob;
use App\Jobs\GeolocationProcessJob;
use App\Jobs\ImageProcessJob;
use App\Jobs\MetadataProcessJob;
use App\Jobs\ThumbnailProcessJob;
use App\Models\Image;
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
            ];
        }

        $statuses = [];
        $statuses['image'] = $this->dispatchImageProcess($image);
        $statuses['thumbnail'] = $this->dispatchThumbnail($image);
        $statuses['metadata'] = $this->dispatchMetadata($image);
        $statuses['face'] = $this->dispatchFace($image);

        Log::info('Dispatch summary', [
            'image_id' => $image->id,
            'mode' => $mode,
            'dry_run' => $dryRun,
            'statuses' => $statuses,
        ]);

        return $statuses;
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
