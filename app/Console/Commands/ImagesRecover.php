<?php

namespace App\Console\Commands;

use App\Enums\ImageStatusEnum;
use App\Jobs\ImageProcessJob;
use App\Models\Image;
use App\Services\ImagePathService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ImagesRecover extends Command
{
    protected $signature = 'images:recover
                            {--dry-run : Show what would be done without doing it}
                            {--limit= : Limit number of images to check}
                            {--verbose : Show detailed info for each image}';

    protected $description = 'Recover images: cleanup orphaned JPG, restore from JPG, mark broken';

    private int $orphanedJpgDeleted = 0;
    private int $restoredFromJpg = 0;
    private int $markedBroken = 0;
    private int $alreadyOk = 0;
    private int $errors = 0;

    public function __construct(
        protected ImagePathService $pathService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        $verbose = $this->option('verbose');

        if ($dryRun) {
            $this->warn('🧪 DRY-RUN mode enabled - no changes will be made');
        }

        $this->info('🔧 Starting image recovery...');
        $this->newLine();

        // Получаем изображения для проверки
        $query = Image::query();

        if ($limit) {
            $query->limit((int)$limit);
            $this->line("Limit: {$limit} images");
        }

        $images = $query->get();
        $total = $images->count();

        if ($total === 0) {
            $this->warn('No images found');
            return CommandAlias::SUCCESS;
        }

        $this->info("Checking {$total} images...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        foreach ($images as $image) {
            $progressBar->setMessage("Checking ID: {$image->id}");

            try {
                $this->recoverImage($image, $dryRun, $verbose);
            } catch (\Exception $e) {
                $this->errors++;
                Log::error('Image recovery failed', [
                    'image_id' => $image->id,
                    'error' => $e->getMessage()
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Статистика
        $this->displayStatistics($dryRun);

        return CommandAlias::SUCCESS;
    }

    /**
     * Восстановить одно изображение
     */
    private function recoverImage(Image $image, bool $dryRun, bool $verbose): void
    {
        // Определяем пути к файлам
        $currentFilename = $image->filename;

        // Если filename содержит .webp
        if (str_ends_with($currentFilename, '.webp')) {
            $webpPath = $this->pathService->getImagePathByObj($image);
            $jpgFilename = pathinfo($currentFilename, PATHINFO_FILENAME) . '.jpg';
            $jpgPath = $this->pathService->getImagePathByParams($image->disk, $image->path, $jpgFilename);

            $this->handleWebpCase($image, $webpPath, $jpgPath, $jpgFilename, $dryRun, $verbose);

        } else {
            // filename не .webp - проверяем что файл существует
            $filePath = $this->pathService->getImagePathByObj($image);

            if (file_exists($filePath)) {
                $this->alreadyOk++;
                if ($verbose) {
                    $this->line("\n✅ OK: ID {$image->id} - file exists");
                }
            } else {
                // Файл не существует
                $this->handleMissingFile($image, $dryRun, $verbose);
            }
        }
    }

    /**
     * Обработать случай когда filename = .webp
     */
    private function handleWebpCase(Image $image, string $webpPath, string $jpgPath, string $jpgFilename, bool $dryRun, bool $verbose): void
    {
        $webpExists = file_exists($webpPath);
        $jpgExists = file_exists($jpgPath);

        // Случай 1: WebP ✅ + JPG ✅ → Удалить JPG (orphaned)
        if ($webpExists && $jpgExists) {
            if ($verbose) {
                $this->line("\n🗑️  Orphaned JPG: ID {$image->id} - {$jpgFilename}");
            }

            if (!$dryRun) {
                unlink($jpgPath);
                Log::info('Orphaned JPG deleted', [
                    'image_id' => $image->id,
                    'jpg_path' => $jpgPath
                ]);
            }

            $this->orphanedJpgDeleted++;
            return;
        }

        // Случай 2: WebP ❌ + JPG ✅ → Восстановить из JPG
        if (!$webpExists && $jpgExists) {
            if ($verbose) {
                $this->line("\n♻️  Restore from JPG: ID {$image->id} - {$jpgFilename}");
            }

            if (!$dryRun) {
                // Сбрасываем filename обратно на .jpg
                $image->filename = $jpgFilename;
                $image->hash = null;
                $image->phash = null;
                $image->save();

                // Dispatch ImageProcessJob для переобработки
                ImageProcessJob::dispatch(['image_id' => $image->id])
                    ->onQueue(config('queue.name.images'));

                Log::info('Image restored from JPG', [
                    'image_id' => $image->id,
                    'jpg_path' => $jpgPath
                ]);
            }

            $this->restoredFromJpg++;
            return;
        }

        // Случай 3: WebP ❌ + JPG ❌ → Пометить broken
        if (!$webpExists && !$jpgExists) {
            if ($verbose) {
                $this->line("\n❌ Missing files: ID {$image->id} - both WebP and JPG not found");
            }

            if (!$dryRun) {
                $image->last_error = 'Files missing: both WebP and JPG not found';
                $image->status = ImageStatusEnum::Recheck->value;
                $image->save();

                Log::error('Image files missing', [
                    'image_id' => $image->id,
                    'webp_path' => $webpPath,
                    'jpg_path' => $jpgPath
                ]);
            }

            $this->markedBroken++;
            return;
        }

        // Случай 4: WebP ✅ + JPG ❌ → Всё ОК
        if ($webpExists && !$jpgExists) {
            $this->alreadyOk++;
            if ($verbose) {
                $this->line("\n✅ OK: ID {$image->id} - WebP exists, JPG deleted");
            }
        }
    }

    /**
     * Обработать случай когда файл отсутствует (filename не .webp)
     */
    private function handleMissingFile(Image $image, bool $dryRun, bool $verbose): void
    {
        if ($verbose) {
            $this->line("\n❌ Missing file: ID {$image->id} - {$image->filename}");
        }

        if (!$dryRun) {
            $image->last_error = "File missing: {$image->filename} not found";
            $image->status = ImageStatusEnum::Recheck->value;
            $image->save();

            Log::error('Image file missing', [
                'image_id' => $image->id,
                'filename' => $image->filename
            ]);
        }

        $this->markedBroken++;
    }

    /**
     * Вывести статистику
     */
    private function displayStatistics(bool $dryRun): void
    {
        $this->info('📊 Recovery statistics:');

        $this->table(
            ['Action', 'Count'],
            [
                ['Already OK', $this->alreadyOk],
                ['Orphaned JPG deleted', $this->orphanedJpgDeleted],
                ['Restored from JPG', $this->restoredFromJpg],
                ['Marked as broken', $this->markedBroken],
                ['Errors', $this->errors],
            ]
        );

        if ($dryRun) {
            $this->warn('🧪 DRY-RUN: No changes were made');
        } else {
            $this->info('✅ Recovery completed!');
        }

        // Предложения по дальнейшим действиям
        if ($this->markedBroken > 0) {
            $this->newLine();
            $this->warn("⚠️  Found {$this->markedBroken} broken images");
            $this->line('To find them: SELECT * FROM images WHERE status = "recheck" AND last_error LIKE "%missing%"');
        }

        if ($this->restoredFromJpg > 0) {
            $this->newLine();
            $this->info("♻️  Restored {$this->restoredFromJpg} images from JPG - they will be reprocessed by workers");
        }
    }
}
