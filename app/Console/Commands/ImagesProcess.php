<?php

namespace App\Console\Commands;

use App\Jobs\BaseProcessJob;
use App\Jobs\ImageProcessJob;
use App\Models\Image;
use App\Services\ApiClient;
use App\Services\ImagePathService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
                if (Image::where('disk', $diskLabel)->where('path', $source)->where('filename', $file)->exists()) {
                    $this->info('Skipped file, exists: ' . $diskLabel . '//' . $source . '/' . $file);
                    continue;
                }
                $this->processImage($diskLabel, $source, $file);
            } catch (\Exception $e) {
                $this->warn('Failed to process image ' . $file . ': ' . $e->getMessage());
                continue;
            }
        }

        // Рекурсивно обрабатываем подкаталоги
        foreach ($directories as $subDir) {
            $basename = basename($subDir);

            // исключаем debug и подпапки формата WIDTHxHEIGHT (например 300x200)
            if (
                strtolower($basename) === 'debug' ||
                preg_match('/^\d+x\d+$/', $basename)
            ) {
                $this->info('Skipped directory: ' . $subDir);
                continue;
            }

            $this->processDirectory($diskLabel, $subDir);
        }
    }

    private function processImage(string $diskLabel, string $sourcePath, string $filename)
    {
        $preparedData = Image::prepareData($diskLabel, $sourcePath, $filename);
        $image = Image::updateInsert($preparedData);

        if (!$image) {
            Log::error('Failed to insert image', [
                'disk' => config('image.paths.disk'),
                'path' => $sourcePath,
                'filename' => $filename
            ]);
            $this->error('Failed to insert image');
        } else {
            $response = BaseProcessJob::pushToQueue(
                ImageProcessJob::class,
                config('queue.name.images'),
                [
                    'image_id' => $image->id,
                ]
            );

            $responseData = $response->getData();
            if ($responseData->status === 'success') {
                Log::info('Image job queued', ['image_id' => $image->id]);
                $this->info('Image job queued: ID = ' . $image->id);
            } elseif ($responseData->status === 'exists') {
                Log::info('Image job already in queue', ['image_id' => $image->id]);
                $this->info('Image job already in queue: ID = ' . $image->id);
            }
        }
    }
}
