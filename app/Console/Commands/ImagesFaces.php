<?php

namespace App\Console\Commands;

use App\Contracts\ImageQueueDispatcherInterface;
use App\Models\Image;
use Illuminate\Console\Command;

class ImagesFaces extends Command
{
    protected $signature = 'images:faces';

    protected $description = 'Queue face detection for images not yet processed';

    public function __construct(
        protected ImageQueueDispatcherInterface $dispatcher
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Image::where('faces_checked', 0);
        $total = $query->count();

        if ($total === 0) {
            $this->info('No images awaiting face detection.');
            return Command::SUCCESS;
        }

        $this->info("Found {$total} images for face detection.");

        $queued = 0;
        $skipped = 0;
        $errors = 0;

        $query->chunkById(1000, function ($images) use (&$queued, &$skipped, &$errors) {
            foreach ($images as $image) {
                try {
                    $status = $this->dispatcher->dispatchFace($image);

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
