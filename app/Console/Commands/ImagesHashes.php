<?php

namespace App\Console\Commands;

use App\Models\Image;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;
use App\Services\ImagePathService;
use Illuminate\Console\Command;

class ImagesHashes extends Command
{
    protected $signature = 'images:hashes';

    protected $description = 'Create images pHashes';

    public function handle()
    {
        $images = Image::query()->get(['id', 'disk', 'path', 'filename', 'hash']);
        $hashes = collect([]);
        $phashes = collect([]);
        foreach ($images as $image) {
            $imagePath = ImagePathService::getImagePath($image);
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
                    if ($distance < 5) {
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
