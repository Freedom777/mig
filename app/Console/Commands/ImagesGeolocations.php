<?php

namespace App\Console\Commands;

use App\Contracts\ImageQueueDispatcherInterface;
use App\Models\Image;
use Illuminate\Console\Command;

class ImagesGeolocations extends Command
{
    protected $signature = 'images:geolocations';

    protected $description = 'Queue geolocation processing for images with GPS data but without geolocation point';

    public function __construct(
        protected ImageQueueDispatcherInterface $dispatcher
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Image::query()
            ->whereNotNull('metadata')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('metadata->GPSLatitude')
                        ->whereNotNull('metadata->GPSLongitude');
                })->orWhereNotNull('metadata->GPSPosition');
            })
            ->whereNull('image_geolocation_point_id');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No images with GPS data awaiting geolocation processing.');
            return Command::SUCCESS;
        }

        $this->info("Found {$total} images with GPS data.");

        $queued = 0;
        $skipped = 0;
        $errors = 0;

        $query->chunkById(1000, function ($images) use (&$queued, &$skipped, &$errors) {
            foreach ($images as $image) {
                try {
                    $status = $this->dispatcher->dispatchGeolocation($image);

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
