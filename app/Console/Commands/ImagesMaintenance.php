<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Services\ImagePathService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ImagesMaintenance extends Command
{
    protected $signature = 'images:maintenance
                            {action=all : Action to perform (check, cleanup, phash, all)}
                            {--dry-run : Show what would be done without doing it}
                            {--limit= : Limit for phash generation}';

    protected $description = 'Maintenance tasks: check files, cleanup unused, generate phashes';

    public function __construct(
        protected ImagePathService $imagePathService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn("🧪 DRY-RUN mode enabled");
        }
        
        $actions = match($action) {
            'check' => ['check'],
            'cleanup' => ['cleanup'],
            'phash' => ['phash'],
            'all' => ['check', 'cleanup', 'phash'],
            default => null
        };
        
        if (!$actions) {
            $this->error("Invalid action: {$action}");
            $this->line('Valid actions: check, cleanup, phash, all');
            return CommandAlias::FAILURE;
        }
        
        foreach ($actions as $task) {
            match($task) {
                'check' => $this->checkFiles(),
                'cleanup' => $this->cleanupUnused($dryRun),
                'phash' => $this->generatePhashes($dryRun),
            };
        }
        
        return CommandAlias::SUCCESS;
    }

    /**
     * Проверка существования файлов
     */
    private function checkFiles(): void
    {
        $this->info('🔍 Checking image files...');
        
        $images = Image::all();
        $missing = [
            'images' => 0,
            'debug' => 0,
            'thumbnails' => 0,
        ];
        $orphanedJpg = 0;
        
        foreach ($images as $image) {
            // Проверка основного файла (WebP или JPG)
            $imagePath = $image->filename
                ? $this->imagePathService->getImagePathByObj($image)
                : null;
            
            if (!$imagePath || !is_file($imagePath)) {
                $this->line("❌ Image missing (ID: {$image->id}): " . ($imagePath ?? '[no filename]'));
                $missing['images']++;
            }
            
            // Проверка orphaned JPG (если filename .webp, но JPG существует)
            if ($imagePath && str_ends_with($image->filename, '.webp')) {
                $jpgFilename = pathinfo($image->filename, PATHINFO_FILENAME) . '.jpg';
                $jpgPath = $this->imagePathService->getImagePathByParams($image->disk, $image->path, $jpgFilename);
                
                if (file_exists($jpgPath)) {
                    $this->line("⚠️  Orphaned JPG (ID: {$image->id}): {$jpgFilename}");
                    $orphanedJpg++;
                }
            }
            
            // Проверка debug файла
            $debugImagePath = $image->debug_filename
                ? $this->imagePathService->getDebugImagePath($image)
                : null;
            
            if ($debugImagePath && !is_file($debugImagePath)) {
                $this->line("⚠️  Debug missing (ID: {$image->id}): {$debugImagePath}");
                $missing['debug']++;
            }
            
            // Проверка thumbnail
            $thumbnailPath = $image->thumbnail_filename
                ? $this->imagePathService->getDefaultThumbnailPath($image)
                : null;
            
            if ($thumbnailPath && !is_file($thumbnailPath)) {
                $this->line("⚠️  Thumbnail missing (ID: {$image->id}): {$thumbnailPath}");
                $missing['thumbnails']++;
            }
        }
        
        $this->newLine();
        $this->info('✅ Check completed:');
        $this->line("  - Missing images: {$missing['images']}");
        $this->line("  - Missing debug: {$missing['debug']}");
        $this->line("  - Missing thumbnails: {$missing['thumbnails']}");
        $this->line("  - Orphaned JPG files: {$orphanedJpg}");
        
        if ($missing['images'] > 0) {
            $this->warn("⚠️  Found {$missing['images']} missing image files!");
        }
        
        if ($orphanedJpg > 0) {
            $this->warn("⚠️  Found {$orphanedJpg} orphaned JPG files!");
            $this->line("Run: php artisan images:recover");
        }
    }

    /**
     * Очистка неиспользуемых debug файлов
     */
    private function cleanupUnused(bool $dryRun): void
    {
        $this->info('🧹 Cleaning up unused debug files...');
        
        $usedPaths = [];
        $images = Image::all();
        
        // Собираем используемые пути
        foreach ($images as $img) {
            if (!$img->debug_filename) {
                continue;
            }
            
            $disk = $img->disk;
            $basePath = trim($img->path, '/');
            $debugPath = $basePath . '/' . $this->imagePathService->getImageDebugSubdir() . '/' . ltrim($img->debug_filename, '/');
            
            $usedPaths[$disk][] = $debugPath;
        }
        
        $deletedCount = 0;
        $unusedCount = 0;
        
        foreach ($usedPaths as $disk => $validPaths) {
            $this->line("📦 Checking disk: {$disk}");
            
            if (!Storage::disk($disk)->exists('/')) {
                $this->warn("❗ Disk {$disk} not accessible");
                continue;
            }
            
            // Собираем debug папки
            $folders = collect($images)
                ->where('disk', $disk)
                ->pluck('path')
                ->map(fn($path) => trim($path, '/') . '/' . $this->imagePathService->getImageDebugSubdir())
                ->unique();
            
            $debugFiles = collect();
            
            foreach ($folders as $folder) {
                if (!Storage::disk($disk)->exists($folder)) {
                    continue;
                }
                
                $debugFiles = $debugFiles->merge(Storage::disk($disk)->files($folder));
            }
            
            $used = collect($validPaths);
            $unusedFiles = $debugFiles->unique()->diff($used);
            
            if ($unusedFiles->isEmpty()) {
                $this->line("✅ No unused debug files");
                continue;
            }
            
            foreach ($unusedFiles as $file) {
                if ($dryRun) {
                    $this->line("🟡 Would delete: {$file}");
                } else {
                    Storage::disk($disk)->delete($file);
                    $this->line("🗑  Deleted: {$file}");
                    $deletedCount++;
                }
                $unusedCount++;
            }
        }
        
        $this->newLine();
        if ($dryRun) {
            $this->info("🧪 DRY-RUN: Would delete {$unusedCount} files");
        } else {
            $this->info("✅ Deleted {$deletedCount} unused debug files");
        }
    }

    /**
     * Генерация pHash для изображений
     */
    private function generatePhashes(bool $dryRun): void
    {
        $this->info('🔢 Generating pHashes...');
        
        $limit = $this->option('limit');
        $query = Image::whereNull('phash');
        
        if ($limit) {
            $query->limit((int)$limit);
            $this->line("Limit: {$limit} images");
        }
        
        $images = $query->get(['id', 'disk', 'path', 'filename', 'hash', 'phash']);
        
        if ($images->isEmpty()) {
            $this->info('✅ All images already have pHash');
            return;
        }
        
        $this->line("Found {$images->count()} images without pHash");
        
        if ($dryRun) {
            $this->warn("🧪 DRY-RUN: Would generate pHash for {$images->count()} images");
            return;
        }
        
        $progressBar = $this->output->createProgressBar($images->count());
        $progressBar->start();
        
        $hasher = new ImageHash(new PerceptualHash());
        $threshold = config('image.processing.phash_distance_threshold', 5);
        $generated = 0;
        $errors = 0;
        
        $hashes = collect();
        $phashes = collect();
        
        foreach ($images as $image) {
            $imagePath = $this->imagePathService->getImagePathByObj($image);
            
            if (!is_file($imagePath)) {
                $errors++;
                $progressBar->advance();
                continue;
            }
            
            try {
                // Проверка на дубликаты по MD5
                $checkHash = $hashes->firstWhere('hash', $image->hash);
                if ($checkHash) {
                    $image->parent_id = $checkHash['id'];
                }
                
                // Генерация pHash
                $phashCurrent = $hasher->hash($imagePath);
                
                // Поиск похожих по pHash (если нет дубликата по MD5)
                if (!$checkHash) {
                    foreach ($phashes as $phash) {
                        $distance = $hasher->distance($phashCurrent, $phash['phash']);
                        if ($distance < $threshold) {
                            $image->parent_id = $phash['id'];
                            break;
                        }
                    }
                }
                
                $hashes->push(['id' => $image->id, 'hash' => $image->hash]);
                $phashes->push(['id' => $image->id, 'phash' => $phashCurrent]);
                
                $image->phash = $phashCurrent;
                $image->save();
                
                $generated++;
            } catch (\Exception $e) {
                $this->error("Error processing image {$image->id}: " . $e->getMessage());
                $errors++;
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        $this->info("✅ pHash generation completed:");
        $this->line("  - Generated: {$generated}");
        $this->line("  - Errors: {$errors}");
    }
}
