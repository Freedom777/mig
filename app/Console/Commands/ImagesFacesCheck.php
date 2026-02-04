<?php

namespace App\Console\Commands;

use App\Contracts\ImagePathServiceInterface;
use App\Contracts\ImageQueueDispatcherInterface;
use App\Models\Face;
use App\Models\Image;
use Illuminate\Console\Command;

class ImagesFacesCheck extends Command
{
    protected $signature = 'images:faces:check';

    protected $description = 'Reprocess faces for images marked for recheck or with missing debug images';

    public function __construct(
        protected ImageQueueDispatcherInterface $dispatcher,
        protected ImagePathServiceInterface $pathService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $images = Image::where('faces_checked', 1)
            ->orWhere('status', Image::STATUS_RECHECK)
            ->get();

        if ($images->isEmpty()) {
            $this->info('No images to recheck.');
            return Command::SUCCESS;
        }

        $this->info("Found {$images->count()} images to check.");

        $reprocessed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($images as $image) {
            $debugImagePath = $this->pathService->getDebugImagePath($image);
            $needsReprocess = $image->status === Image::STATUS_RECHECK
                || !$debugImagePath
                || !is_file($debugImagePath);

            if (!$needsReprocess) {
                $skipped++;
                continue;
            }

            try {
                $this->reprocessFaces($image, $debugImagePath);
                $reprocessed++;
            } catch (\Exception $e) {
                $this->error("Failed for image {$image->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->info("Completed: {$reprocessed} reprocessed, {$skipped} skipped, {$errors} errors.");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function reprocessFaces(Image $image, ?string $debugImagePath): void
    {
        // Переназначаем parent_id у дочерних лиц перед удалением
        $faces = $image->faces;
        foreach ($faces as $face) {
            $children = $face->children;
            if ($children->count()) {
                // Первый ребёнок становится новым parent
                $newParent = $children->shift();
                $newParent->parent_id = null;
                $newParent->save();

                // Остальные дети переносятся к новому parent
                foreach ($children as $child) {
                    $child->parent_id = $newParent->id;
                    $child->save();
                }
            }
        }

        // Удаляем все лица для этого изображения
        Face::where('image_id', $image->id)->forceDelete();

        // Удаляем debug файл если есть и это recheck
        if ($image->status === Image::STATUS_RECHECK && $debugImagePath && file_exists($debugImagePath)) {
            unlink($debugImagePath);
        }

        // Сбрасываем флаги
        $image->update([
            'faces_checked' => 0,
            'debug_filename' => null,
            'status' => Image::STATUS_PROCESS,
        ]);

        // Ставим в очередь на переобработку
        $this->dispatcher->dispatchFace($image);
    }
}
