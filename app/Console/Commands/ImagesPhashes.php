<?php

namespace App\Console\Commands;

use App\Models\Image;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;
use App\Services\ImagePathService;
use Illuminate\Console\Command;

class ImagesPhashes extends Command
{
    protected $signature = 'images:phashes';

    protected $description = 'Create images pHashes';

    public const PHASH_DISTANCE_THRESHOLD = 5;

    public function handle()
    {
        $images = Image::query()->get(['id', 'hash']);
        $hashes = collect([]);
        $phashes = collect([]);
        foreach ($images as $image) {
            $imagePath = ImagePathService::getImagePathByObj($image);
            if (!is_file($imagePath)) {
                continue;
            }
            $checkHash = $hashes->firstWhere('hash', $image->hash);
            if ($checkHash) {
                $image->parent_id = $checkHash['id'];
            }
            $hasher = new ImageHash(new PerceptualHash());
            $phashCurrent = $hasher->hash($imagePath);
            if (!$checkHash) {
                foreach ($phashes as $phash) {
                    $distance = $hasher->distance($phashCurrent, $phash['phash']);
                    if ($distance < self::PHASH_DISTANCE_THRESHOLD) {
                        $image->parent_id = $phash['id'];
                    }
                }
            }

            $hashes->push(['id' => $image->id, 'hash' => $image->hash]);
            $phashes->push(['id' => $image->id, 'phash' => $phashCurrent]);

            $image->phash = $phashCurrent;
            $image->save();
        }

        $this->info('Images hashes completed');
        return 0;
    }
}
