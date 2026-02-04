<?php

namespace App\Console\Commands;

use App\Contracts\ImageQueueDispatcherInterface;
use App\Models\Image;
use Illuminate\Console\Command;

class ImagesMetadatas extends Command
{
    protected $signature = 'images:metadatas';

    protected $description = 'Queue metadata extraction for images without metadata';

    public function __construct(
        protected ImageQueueDispatcherInterface $dispatcher
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Image::whereNull('metadata');
        $total = $query->count();

        if ($total === 0) {
            $this->info('No images without metadata found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$total} images without metadata.");

        $queued = 0;
        $skipped = 0;
        $errors = 0;

        $query->chunkById(1000, function ($images) use (&$queued, &$skipped, &$errors) {
            foreach ($images as $image) {
                try {
                    $status = $this->dispatcher->dispatchMetadata($image);

                    match ($status) {
                        'success', 'completed' => $queued++,
                        'exists', 'skipped', 'dry-run' => $skipped++,
                        default => $errors++,
                    };

                } catch (\Exception $e) {
                    $this->error("Failed for image {$image->id}: {$e->getMessage()}");
                    $errors++;
                }
            }
        });

        $this->info("Completed: {$queued} queued, {$skipped} skipped, {$errors} errors.");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
