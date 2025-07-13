<?php

namespace App\Console\Commands;

use App\Services\ApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImagesProcess extends Command
{
    protected $signature = 'images:process
                            {disk : Source disk}
                            {source : Source directory with images}
                            {--target= : Target subdirectory in storage}';

    protected $description = 'Process images: copy, get basic info';

    protected ApiClient $apiClient;

    public function __construct(ApiClient $apiClient)
    {
        parent::__construct();
        $this->apiClient = $apiClient;
    }

    public function handle()
    {
        $diskLabel = $this->argument('disk') ?? 'local';
        $disk = Storage::disk($diskLabel);
        $source = $this->argument('source') ?? '.';

        if (!is_dir($disk->path($source))) {
            $this->error('It\'s not a directory, disk ' . $diskLabel . ', directory: ' . $source);
            return 1;
        }

        // Обрабатываем исходную директорию рекурсивно
        $this->processDirectory($diskLabel, $source);
        $this->info('Image processing completed');
        return 0;

        // Запускаем команды для дополнительной обработки
        /*$this->call('images:extract-metadata', [
            '--path' => storage_path('app/' . env('IMAGE_STORAGE_DISK') . '/' . $targetImagesDir)
        ]);*/

        // $this->call('images:generate-thumbnails');
    }

    protected function processDirectory(string $diskLabel, string $source)
    {
        $disk = Storage::disk($diskLabel);
        $directories = $disk->directories($source);

        $files = array_map('basename', array_filter($disk->files($source), function ($item) {
            $lowerItem = strtolower($item);
            return str_ends_with($lowerItem, '.jpg') || str_ends_with($lowerItem, '.jpeg');
        }));

        if (!$directories && !$files){
            $this->warn('Nothing to process in directory, disk ' . $diskLabel . ', directory: ' . $source);
            return;
        }

        foreach ($files as $file) {
            try {
                $this->processImage($diskLabel, $source, $file);
            } catch (\Exception $e) {
                $this->warn('Failed to process image ' . $file . ': ' . $e->getMessage());
                continue;
            }
        }

        // Рекурсивно обрабатываем подкаталоги
        foreach ($directories as $subDir) {
            $this->processDirectory($diskLabel, $subDir);
        }
    }

    private function processImage(string $diskLabel, string $sourcePath, string $filename)
    {
        $disk = Storage::disk($diskLabel);
        $file = $disk->path($sourcePath) . '/' . $filename;
        $imagedata = getimagesize($file);

        try {
            $requestData = [
                'source_disk' => $diskLabel,
                'source_path' => $sourcePath,
                'source_filename' => $filename,
                'width' => $imagedata[0],
                'height' => $imagedata[1],
                'size' => filesize($file),
                'hash' => md5_file($file),
                'created_at_file' => date('Y-m-d H:i:s', filectime($file)),
                'updated_at_file' => date('Y-m-d H:i:s', filemtime($file)),
            ];
            $response = $this->apiClient->imageProcess($requestData);

            if ($response->successful()) {
                $this->info('Task queued: ' . $diskLabel . '://' . $sourcePath . '/' . $filename);
            } else {
                $this->error('API error (' . $diskLabel . '://' . $sourcePath . '/' . $filename . '): ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Failed to send to API (' . $diskLabel . '://' . $sourcePath . '/' . $filename . '): ' . $e->getMessage());
        }
    }
}
