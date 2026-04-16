<?php

namespace App\Console\Commands;

use App\Enums\ImageStatusEnum;
use App\Jobs\FaceProcessJob;
use App\Jobs\GeolocationProcessJob;
use App\Jobs\ImageProcessJob;
use App\Jobs\MetadataProcessJob;
use App\Jobs\ThumbnailProcessJob;
use App\Models\Image;
use App\Services\ImagePathService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ImagesReprocess extends Command
{
    protected $signature = 'images:reprocess
                            {--no-debug : Images without debug_filename}
                            {--faces-failed : Images where faces_checked = 0}
                            {--no-metadata : Images without metadata}
                            {--no-thumbnails : Images without thumbnail_path}
                            {--has-gps : Images with GPS but without geolocation}
                            {--no-webp : Images where filename still .jpg (ImageProcessJob failed)}
                            {--no-hash : Images where hash IS NULL (ImageProcessJob failed)}
                            {--orphaned-jpg : Images where filename .webp but JPG exists}
                            {--status=* : Images with specific status (process, recheck, not_photo, ok)}
                            {--limit= : Limit number of images}
                            {--dry-run : Show what would be reprocessed}
                            {--queue=all : Which queue (image, faces, metadata, thumbnails, geolocations, all)}';

    protected $description = 'Reprocess images with errors or missing data';

    public function __construct(
        protected ImagePathService $pathService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Image::query();
        $filtersApplied = 0;

        // Фильтр: без debug_filename
        if ($this->option('no-debug')) {
            $query->where('faces_checked', 1)
                ->whereNull('debug_filename');
            $this->info('Filter: faces_checked=1 AND debug_filename IS NULL');
            $filtersApplied++;
        }

        // Фильтр: faces_checked = 0
        if ($this->option('faces-failed')) {
            $query->where('faces_checked', 0);
            $this->info('Filter: faces_checked=0');
            $filtersApplied++;
        }

        // Фильтр: без metadata
        if ($this->option('no-metadata')) {
            $query->whereNull('metadata');
            $this->info('Filter: metadata IS NULL');
            $filtersApplied++;
        }

        // Фильтр: без thumbnails
        if ($this->option('no-thumbnails')) {
            $query->whereNull('thumbnail_path');
            $this->info('Filter: thumbnail_path IS NULL');
            $filtersApplied++;
        }

        // Фильтр: с GPS но без geolocation
        if ($this->option('has-gps')) {
            $query->whereNotNull('metadata')
                ->where(function ($q) {
                    $q->where(function ($subQ) {
                        $subQ->whereNotNull('metadata->GPSLatitude')
                             ->whereNotNull('metadata->GPSLongitude');
                    })->orWhereNotNull('metadata->GPSPosition');
                })
                ->whereNull('image_geolocation_point_id');
            $this->info('Filter: has GPS data but no geolocation');
            $filtersApplied++;
        }

        // Фильтр: filename всё ещё .jpg (ImageProcessJob failed)
        if ($this->option('no-webp')) {
            $query->where(function ($q) {
                $q->where('filename', 'like', '%.jpg')
                  ->orWhere('filename', 'like', '%.jpeg');
            });
            $this->info('Filter: filename still .jpg');
            $filtersApplied++;
        }

        // Фильтр: hash IS NULL (ImageProcessJob failed)
        if ($this->option('no-hash')) {
            $query->whereNull('hash');
            $this->info('Filter: hash IS NULL');
            $filtersApplied++;
        }

        // Фильтр: orphaned JPG (filename .webp но JPG файл существует)
        if ($this->option('orphaned-jpg')) {
            $query->where('filename', 'like', '%.webp');
            $this->info('Filter: filename is .webp (will check for orphaned JPG files)');
            $filtersApplied++;
        }

        // Фильтр: по статусу
        $statuses = $this->option('status');
        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
            $this->info('Filter: status IN (' . implode(', ', $statuses) . ')');
            $filtersApplied++;
        }

        // Если не применили ни одного фильтра - показываем подсказку
        if ($filtersApplied === 0) {
            $this->warn('No filters specified. Use --help to see available options.');
            $this->line('');
            $this->line('Examples:');
            $this->line('  php artisan images:reprocess --faces-failed');
            $this->line('  php artisan images:reprocess --no-metadata --queue=metadata');
            $this->line('  php artisan images:reprocess --no-webp --queue=image');
            $this->line('  php artisan images:reprocess --no-hash --queue=image');
            $this->line('  php artisan images:reprocess --status=recheck --limit=100');
            return CommandAlias::SUCCESS;
        }

        // Лимит
        $limit = $this->option('limit');
        if ($limit) {
            $query->limit((int)$limit);
            $this->info("Limit: {$limit} images");
        }

        $images = $query->orderBy('id')->get();

        if ($images->isEmpty()) {
            $this->warn('No images found matching the criteria');
            return CommandAlias::SUCCESS;
        }

        // Если фильтр --orphaned-jpg, дополнительно фильтруем по существованию JPG файла
        if ($this->option('orphaned-jpg')) {
            $imagesWithOrphanedJpg = $images->filter(function ($image) {
                $jpgFilename = pathinfo($image->filename, PATHINFO_FILENAME) . '.jpg';
                $jpgPath = $this->pathService->getImagePathByParams($image->disk, $image->path, $jpgFilename);
                return file_exists($jpgPath);
            });

            if ($imagesWithOrphanedJpg->isEmpty()) {
                $this->warn('No images with orphaned JPG files found');
                return CommandAlias::SUCCESS;
            }

            $images = $imagesWithOrphanedJpg;
            $this->info("Found {$images->count()} images with orphaned JPG files");
        } else {
            $this->info("Found {$images->count()} images to reprocess");
        }

        // Dry run
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - nothing will be queued');

            $tableData = $images->take(20)->map(fn($img) => [
                $img->id,
                $img->filename,
                $img->status,
                $img->faces_checked ? 'Yes' : 'No',
                $img->debug_filename ?? '(null)',
                $img->metadata ? 'Yes' : 'No',
                $img->thumbnail_path ? 'Yes' : 'No',
            ])->toArray();

            $this->table(
                ['ID', 'Filename', 'Status', 'Faces', 'Debug', 'Metadata', 'Thumb'],
                $tableData
            );

            if ($images->count() > 20) {
                $this->line("... and " . ($images->count() - 20) . " more");
            }

            return CommandAlias::SUCCESS;
        }

        // Определяем очереди
        $queueOption = $this->option('queue');

        $progressBar = $this->output->createProgressBar($images->count());
        $progressBar->start();

        $queued = [
            'image' => 0,
            'faces' => 0,
            'metadata' => 0,
            'thumbnails' => 0,
            'geolocations' => 0,
        ];

        foreach ($images as $image) {
            // Image (hash + WebP)
            if ($queueOption === 'all' || $queueOption === 'image') {
                ImageProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue(config('queue.name.images'));
                $queued['image']++;
            }

            // Faces
            if ($queueOption === 'all' || $queueOption === 'faces') {
                FaceProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue('faces');
                $queued['faces']++;
            }

            // Metadata
            if ($queueOption === 'all' || $queueOption === 'metadata') {
                MetadataProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue(config('queue.name.metadatas'));
                $queued['metadata']++;
            }

            // Thumbnails
            if ($queueOption === 'all' || $queueOption === 'thumbnails') {
                ThumbnailProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue(config('queue.name.thumbnails'));
                $queued['thumbnails']++;
            }

            // Geolocations
            if ($queueOption === 'all' || $queueOption === 'geolocations') {
                GeolocationProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue(config('queue.name.geolocations'));
                $queued['geolocations']++;
            }

            // Сбрасываем статус recheck → process
            if ($image->status === ImageStatusEnum::Recheck->value) {
                $image->update(['status' => ImageStatusEnum::Process->value]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Reprocessing queued successfully:');
        foreach ($queued as $queue => $count) {
            if ($count > 0) {
                $this->line("  - " . ucfirst($queue) . ": {$count} jobs");
            }
        }

        return CommandAlias::SUCCESS;
    }
}
