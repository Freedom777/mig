<?php

namespace App\Console\Commands;

use App\Contracts\ImageServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImagesProcess extends Command
{
    protected $signature = 'images:process
                            {disk : Source disk}
                            {source : Source directory with images}
                            {--skip-existing : Skip images that already exist in database}';

    protected $description = 'Process images: copy, get basic info, queue for processing';

    public function __construct(
        protected ImageServiceInterface $imageService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $diskLabel = $this->argument('disk');
        $disk = Storage::disk($diskLabel);
        $source = $this->argument('source');
        $skipExisting = $this->option('skip-existing');

        if (!is_dir($disk->path($source))) {
            $this->error("Not a directory: disk '{$diskLabel}', path: '{$source}'");
            return Command::FAILURE;
        }

        $this->processDirectory($diskLabel, $source, $skipExisting);
        $this->info('Image processing completed');

        return Command::SUCCESS;
    }

    protected function processDirectory(string $diskLabel, string $source, bool $skipExisting): void
    {
        $disk = Storage::disk($diskLabel);
        $directories = $disk->directories($source);

        $files = array_map('basename', array_filter($disk->files($source), function ($item) {
            $lowerItem = strtolower($item);
            return str_ends_with($lowerItem, '.jpg') || str_ends_with($lowerItem, '.jpeg');
        }));

        if (!$directories && !$files) {
            $this->warn("Nothing to process: disk '{$diskLabel}', path: '{$source}'");
            return;
        }

        foreach ($files as $file) {
            $this->processImage($diskLabel, $source, $file, $skipExisting);
        }

        // Рекурсивно обрабатываем подкаталоги
        foreach ($directories as $subDir) {
            $basename = basename($subDir);

            // Исключаем debug и подпапки формата WIDTHxHEIGHT
            if ($this->shouldSkipDirectory($basename)) {
                $this->info("Skipped directory: {$subDir}");
                continue;
            }

            $this->processDirectory($diskLabel, $subDir, $skipExisting);
        }
    }

    protected function processImage(string $disk, string $path, string $filename, bool $skipExisting): void
    {
        try {
            $result = $this->imageService->processNewUpload(
                disk: $disk,
                path: $path,
                filename: $filename,
                skipIfExists: $skipExisting
            );

            if ($result['success']) {
                $this->info("Queued: {$disk}//{$path}/{$filename} (ID: {$result['image']->id})");
            } else {
                $this->warn("Skipped: {$disk}//{$path}/{$filename} - {$result['message']}");
            }

        } catch (\Exception $e) {
            $this->error("Failed: {$disk}//{$path}/{$filename} - {$e->getMessage()}");
        }
    }

    protected function shouldSkipDirectory(string $basename): bool
    {
        // Пропускаем debug директории
        if (strtolower($basename) === 'debug') {
            return true;
        }

        // Пропускаем директории thumbnail (формат WIDTHxHEIGHT)
        if (preg_match('/^\d+x\d+$/', $basename)) {
            return true;
        }

        return false;
    }
}
