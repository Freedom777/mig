<?php

namespace App\Console\Commands;

use App\Enums\ImageStatusEnum;
use App\Jobs\FaceProcessJob;
use App\Jobs\MetadataProcessJob;
use App\Jobs\ThumbnailProcessJob;
use App\Models\Image;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ImagesReprocess extends Command
{
    protected $signature = 'images:reprocess
                            {--no-debug : Reprocess images without debug_filename}
                            {--faces-failed : Reprocess images where faces_checked = 0}
                            {--status=* : Reprocess images with specific status (error, process)}
                            {--limit= : Limit number of images to reprocess}
                            {--dry-run : Show what would be reprocessed without actually doing it}
                            {--queue= : Specify which processing queue (faces, metadata, thumbnails, all)}';

    protected $description = 'Reprocess images with errors or missing data';

    public function handle(): int
    {
        $query = Image::query();
        
        // Фильтр: без debug_filename
        if ($this->option('no-debug')) {
            $query->where('faces_checked', 1)
                ->whereNull('debug_filename');
            $this->info('Filter: images with faces_checked=1 but debug_filename IS NULL');
        }
        
        // Фильтр: faces_checked = 0
        if ($this->option('faces-failed')) {
            $query->where('faces_checked', 0);
            $this->info('Filter: images with faces_checked=0');
        }
        
        // Фильтр: по статусу
        $statuses = $this->option('status');
        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
            $this->info('Filter: status IN (' . implode(', ', $statuses) . ')');
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
        
        // Dry run - показываем что будет обработано
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - nothing will be queued');
            $this->table(
                ['ID', 'Filename', 'Status', 'faces_checked', 'debug_filename'],
                $images->map(fn($img) => [
                    $img->id,
                    $img->filename,
                    $img->status,
                    $img->faces_checked ? 'Yes' : 'No',
                    $img->debug_filename ?? '(null)',
                ])->toArray()
            );
            return CommandAlias::SUCCESS;
        }
        
        // Определяем какие очереди запускать
        $queueOption = $this->option('queue') ?? 'all';
        
        $progressBar = $this->output->createProgressBar($images->count());
        $progressBar->start();
        
        $queued = [
            'faces' => 0,
            'metadata' => 0,
            'thumbnails' => 0,
        ];
        
        foreach ($images as $image) {
            // Очередь faces
            if ($queueOption === 'all' || $queueOption === 'faces') {
                FaceProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue('faces');
                $queued['faces']++;
            }
            
            // Очередь metadata (если нужно)
            if ($queueOption === 'all' || $queueOption === 'metadata') {
                MetadataProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue('metadatas');
                $queued['metadata']++;
            }
            
            // Очередь thumbnails (если нужно)
            if ($queueOption === 'all' || $queueOption === 'thumbnails') {
                ThumbnailProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue('thumbnails');
                $queued['thumbnails']++;
            }
            
            // Сбрасываем статус на Process для повторной обработки
            if ($image->status === ImageStatusEnum::Error->value) {
                $image->update(['status' => ImageStatusEnum::Process->value]);
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        $this->info('Reprocessing queued successfully:');
        if ($queued['faces'] > 0) {
            $this->line("  - Faces: {$queued['faces']} jobs");
        }
        if ($queued['metadata'] > 0) {
            $this->line("  - Metadata: {$queued['metadata']} jobs");
        }
        if ($queued['thumbnails'] > 0) {
            $this->line("  - Thumbnails: {$queued['thumbnails']} jobs");
        }
        
        return CommandAlias::SUCCESS;
    }
}
