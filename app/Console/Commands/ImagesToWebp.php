<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Services\ImagePathService;
use Illuminate\Console\Command;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;

class ImagesToWebp extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'images:convert-to-webp
                            {--dry-run : Показать что будет сконвертировано без реальной конвертации}
                            {--limit= : Ограничить количество изображений для конвертации}
                            {--quality-image=90 : Качество WebP для оригинальных изображений}
                            {--quality-thumbnail=85 : Качество WebP для миниатюр}
                            {--quality-debug=80 : Качество WebP для debug изображений}';

    /**
     * The console command description.
     */
    protected $description = 'Конвертировать все JPG изображения в WebP формат';

    /**
     * Статистика конвертации
     */
    private int $totalImages = 0;
    private int $convertedImages = 0;
    private int $convertedThumbnails = 0;
    private int $convertedDebug = 0;
    private int $errors = 0;
    private int $freedSpace = 0;

    /**
     * Image path service
     */
    private ImagePathService $pathService;

    /**
     * Constructor
     */
    public function __construct(ImagePathService $pathService)
    {
        parent::__construct();
        $this->pathService = $pathService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        $qualityImage = (int) $this->option('quality-image');
        $qualityThumbnail = (int) $this->option('quality-thumbnail');
        $qualityDebug = (int) $this->option('quality-debug');

        $this->info('🚀 Начинаю конвертацию изображений в WebP...');

        if ($isDryRun) {
            $this->warn('⚠️  DRY RUN режим - файлы не будут изменены');
        }

        $this->info("📊 Качество: Images={$qualityImage}, Thumbnails={$qualityThumbnail}, Debug={$qualityDebug}");
        $this->newLine();

        // Получаем все изображения с JPG расширением
        $query = Image::where('filename', 'like', '%.jpg')
            ->orWhere('filename', 'like', '%.jpeg')
            ->orWhere('filename', 'like', '%.JPG')
            ->orWhere('filename', 'like', '%.JPEG');

        if ($limit) {
            $query->limit((int) $limit);
        }

        $images = $query->get();
        $this->totalImages = $images->count();

        if ($this->totalImages === 0) {
            $this->info('✅ Нет изображений для конвертации!');
            return Command::SUCCESS;
        }

        $this->info("📷 Найдено изображений: {$this->totalImages}");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($this->totalImages);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage('Начинаю...');
        $progressBar->start();

        foreach ($images as $image) {
            $progressBar->setMessage("Обработка: {$image->filename}");

            try {
                $this->convertImage($image, $qualityImage, $qualityThumbnail, $qualityDebug, $isDryRun);
            } catch (\Exception $e) {
                $this->errors++;
                $this->error("\n❌ Ошибка при конвертации {$image->filename}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Вывод статистики
        $this->displayStatistics($isDryRun);

        return Command::SUCCESS;
    }

    /**
     * Конвертировать одно изображение и его варианты
     */
    private function convertImage(Image $image, int $qualityImage, int $qualityThumbnail, int $qualityDebug, bool $isDryRun): void
    {
        $disk = Storage::disk($image->disk);

        // 1. Конвертировать основное изображение
        $originalFullPath = $this->pathService->getImagePathByObj($image);
        $originalRelativePath = $image->path . '/' . $image->filename;

        if (file_exists($originalFullPath)) {
            $newFilename = $this->changeExtension($image->filename, 'webp');
            $newRelativePath = $image->path . '/' . $newFilename;

            if (!$isDryRun) {
                $this->convertFile($disk, $originalFullPath, $originalRelativePath, $newRelativePath, $qualityImage);
                $image->filename = $newFilename;
            }
            $this->convertedImages++;
        }

        // 2. Конвертировать thumbnail
        $existingThumbPath = $this->pathService->getExistingThumbnailPath($image);

        if ($existingThumbPath && file_exists($existingThumbPath)) {
            $thumbRelativePath = $image->path . '/' . $image->thumbnail_path . '/' . $image->thumbnail_filename;
            $newThumbFilename = $this->changeExtension($image->thumbnail_filename, 'webp');
            $newThumbRelativePath = $image->path . '/' . $image->thumbnail_path . '/' . $newThumbFilename;

            if (!$isDryRun) {
                $this->convertFile($disk, $existingThumbPath, $thumbRelativePath, $newThumbRelativePath, $qualityThumbnail);
                $image->thumbnail_filename = $newThumbFilename;
            }
            $this->convertedThumbnails++;
        }

        // 3. Конвертировать debug image
        $debugPath = $this->pathService->getDebugImagePath($image);

        if ($debugPath && file_exists($debugPath)) {
            $debugRelativePath = $image->path . '/' . $this->pathService->getImageDebugSubdir() . '/' . $image->debug_filename;
            $newDebugFilename = $this->changeExtension($image->debug_filename, 'webp');
            $newDebugRelativePath = $image->path . '/' . $this->pathService->getImageDebugSubdir() . '/' . $newDebugFilename;

            if (!$isDryRun) {
                $this->convertFile($disk, $debugPath, $debugRelativePath, $newDebugRelativePath, $qualityDebug);
                $image->debug_filename = $newDebugFilename;
            }
            $this->convertedDebug++;
        }

        // Сохранить изменения в БД
        if (!$isDryRun) {
            $image->save();
        }
    }

    /**
     * Конвертировать файл JPG → WebP
     */
    private function convertFile($disk, string $sourceAbsolutePath, string $sourceRelativePath, string $destinationRelativePath, int $quality): void
    {
        // Получить размер оригинального файла
        $originalSize = $disk->size($sourceRelativePath);

        // Создать ImageManager
        $manager = new ImageManager(new Driver());

        // Загрузить изображение (используем абсолютный путь)
        $img = $manager->read($sourceAbsolutePath);

        // Сохранить как WebP
        $webpData = $img->toWebp(quality: $quality);
        $disk->put($destinationRelativePath, (string) $webpData);

        // Получить размер нового файла
        $newSize = $disk->size($destinationRelativePath);
        $this->freedSpace += ($originalSize - $newSize);

        // Удалить оригинальный JPG файл
        $disk->delete($sourceRelativePath);
    }

    /**
     * Изменить расширение файла
     */
    private function changeExtension(string $filename, string $newExtension): string
    {
        $pathInfo = pathinfo($filename);
        return $pathInfo['filename'] . '.' . $newExtension;
    }

    /**
     * Вывести статистику конвертации
     */
    private function displayStatistics(bool $isDryRun): void
    {
        $this->info('📊 Статистика конвертации:');
        $this->table(
            ['Тип', 'Количество'],
            [
                ['Всего изображений', $this->totalImages],
                ['Основные изображения', $this->convertedImages],
                ['Миниатюры', $this->convertedThumbnails],
                ['Debug изображения', $this->convertedDebug],
                ['Ошибки', $this->errors],
            ]
        );

        if (!$isDryRun && $this->freedSpace > 0) {
            $freedSpaceMB = round($this->freedSpace / 1024 / 1024, 2);
            $this->info("💾 Освобождено места: {$freedSpaceMB} MB");
        }

        if ($this->errors > 0) {
            $this->warn("⚠️  Конвертация завершена с ошибками!");
        } else {
            $this->info('✅ Конвертация успешно завершена!');
        }
    }
}
