<?php
namespace App\Console\Commands;

use App\Services\ImagePathService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Image;

class CleanupUnusedImages extends Command
{
    protected $signature = 'images:cleanup-unused {--dry-run : Только показать, какие файлы будут удалены}';
    protected $description = 'Удаляет debug-файлы, которые не используются в таблице images';

    public function __construct(
        protected ImagePathService $imagePathService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info("🧪 Dry-run режим: файлы НЕ будут удалены.");
        }

        $this->info("🔍 Сканируем таблицу images...");

        $usedPaths = [];

        // Собираем пути debug-файлов из таблицы
        $images = Image::all();
        foreach ($images as $img) {
            $disk = $img->disk;
            $basePath = trim($img->path, '/');
            $debugPath = $basePath . '/' . $this->imagePathService->getImageDebugSubdir() . '/' . ltrim($img->debug_filename, '/');

            $usedPaths[$disk][] = $debugPath;
        }

        $deletedCount = 0;
        $unusedCount = 0;

        foreach ($usedPaths as $disk => $validPaths) {
            $this->info("📦 Проверка диска: $disk");

            if (!Storage::disk($disk)->exists('/')) {
                $this->warn("❗ Диск $disk не существует или недоступен");
                continue;
            }

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
                $this->info("✅ Лишние debug-файлы не найдены");
                continue;
            }

            foreach ($unusedFiles as $file) {
                if ($dryRun) {
                    $this->line("🟡 Был бы удалён: $file");
                } else {
                    Storage::disk($disk)->delete($file);
                    $this->line("🗑 Удалён: $file");
                    $deletedCount++;
                }
                $unusedCount++;
            }
        }

        if ($dryRun) {
            $this->info("🧪 Dry-run завершён. Лишних файлов: $unusedCount");
        } else {
            $this->info("✨ Удалено файлов: $deletedCount");
        }

        return 0;
    }
}
