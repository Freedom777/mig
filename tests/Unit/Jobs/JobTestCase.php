<?php

namespace Tests\Unit\Jobs;

use App\Models\Image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Базовый класс для тестирования jobs
 */
abstract class JobTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Используем fake storage
        Storage::fake('public');
    }

    /**
     * Создаёт тестовое JPG изображение
     */
    protected function createTestImage(array $attributes = []): Image
    {
        // Создаём тестовый JPG файл
        $filename = $attributes['filename'] ?? 'test_image.jpg';
        $path = $attributes['path'] ?? 'images';
        
        // Создаём реальный JPG файл (1x1 пиксель)
        $jpgContent = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');
        
        Storage::disk('public')->put("{$path}/{$filename}", $jpgContent);
        
        return Image::create(array_merge([
            'disk' => 'public',
            'path' => $path,
            'filename' => $filename,
            'point_id' => null,
        ], $attributes));
    }

    /**
     * Получает реальный путь к файлу в fake storage
     */
    protected function getStoragePath(Image $image): string
    {
        return Storage::disk($image->disk)->path("{$image->path}/{$image->filename}");
    }

    /**
     * Проверяет что файл существует
     */
    protected function assertFileExists(Image $image): void
    {
        $this->assertTrue(
            Storage::disk($image->disk)->exists("{$image->path}/{$image->filename}"),
            "File {$image->filename} does not exist"
        );
    }

    /**
     * Проверяет что файл НЕ существует
     */
    protected function assertFileNotExists(Image $image, string $filename = null): void
    {
        $file = $filename ?? $image->filename;
        $this->assertFalse(
            Storage::disk($image->disk)->exists("{$image->path}/{$file}"),
            "File {$file} should not exist"
        );
    }

    /**
     * Устанавливает все необходимые флаги для запуска ImageProcessJob
     */
    protected function setImageReadyForProcessing(Image $image): Image
    {
        $image->update([
            'metadata' => ['camera' => 'Test Camera'],
            'faces_checked' => true,
            'thumbnail_filename' => 'test_thumb.webp',
        ]);
        
        return $image->fresh();
    }
}
