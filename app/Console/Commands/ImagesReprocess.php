<?php

namespace App\Console\Commands;

use App\Enums\ImageStatusEnum;
use App\Jobs\FaceProcessJob;
use App\Jobs\GeolocationProcessJob;
use App\Jobs\MetadataProcessJob;
use App\Jobs\ThumbnailProcessJob;
use App\Models\Image;
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
                            {--status=* : Images with specific status (process, recheck, not_photo, ok)}
                            {--limit= : Limit number of images}
                            {--dry-run : Show what would be reprocessed}
                            {--queue=all : Which queue (faces, metadata, thumbnails, geolocations, all)}';

    protected $description = 'Reprocess images with errors or missing data';

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
        
        $this->info("Found {$images->count()} images to reprocess");
        
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
            'faces' => 0,
            'metadata' => 0,
            'thumbnails' => 0,
            'geolocations' => 0,
        ];
        
        foreach ($images as $image) {
            // Faces
            if ($queueOption === 'all' || $queueOption === 'faces') {
                FaceProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue('faces');
                $queued['faces']++;
            }
            
            // Metadata
            if ($queueOption === 'all' || $queueOption === 'metadata') {
                MetadataProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue('metadatas');
                $queued['metadata']++;
            }
            
            // Thumbnails
            if ($queueOption === 'all' || $queueOption === 'thumbnails') {
                ThumbnailProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue('thumbnails');
                $queued['thumbnails']++;
            }
            
            // Geolocations
            if ($queueOption === 'all' || $queueOption === 'geolocations') {
                GeolocationProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue('geolocations');
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
