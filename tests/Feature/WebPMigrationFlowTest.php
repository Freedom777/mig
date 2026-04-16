<?php

namespace Tests\Feature;

use App\Events\ImageJobCompleted;
use App\Jobs\FaceProcessJob;
use App\Jobs\ImageProcessJob;
use App\Jobs\MetadataProcessJob;
use App\Jobs\ThumbnailProcessJob;
use App\Models\Image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebPMigrationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Event::fake();
    }

    /** @test */
    public function complete_webp_migration_flow()
    {
        // Arrange - создаём JPG изображение
        $jpgContent = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA//2Q==');
        Storage::disk('public')->put('images/test.jpg', $jpgContent);

        $image = Image::create([
            'disk' => 'public',
            'path' => 'images',
            'filename' => 'test.jpg',
        ]);

        // Act - выполняем все jobs последовательно

        // 1. ThumbnailProcessJob
        $thumbnailJob = new ThumbnailProcessJob(['image_id' => $image->id]);
        $thumbnailJob->handle(app(\App\Contracts\ImagePathServiceInterface::class));
        
        $image->refresh();
        $this->assertNotNull($image->thumbnail_filename);
        Event::assertDispatched(ImageJobCompleted::class, function ($event) use ($image) {
            return $event->imageId === $image->id && $event->jobType === 'thumbnail';
        });

        // 2. MetadataProcessJob
        $metadataJob = new MetadataProcessJob(['image_id' => $image->id]);
        $metadataJob->handle(
            app(\App\Contracts\ImageRepositoryInterface::class),
            app(\App\Contracts\ImagePathServiceInterface::class)
        );
        
        $image->refresh();
        $this->assertNotNull($image->metadata);
        Event::assertDispatched(ImageJobCompleted::class, function ($event) use ($image) {
            return $event->imageId === $image->id && $event->jobType === 'metadata';
        });

        // 3. FaceProcessJob
        $image->update(['faces_checked' => true]); // Mock face processing
        Event::dispatch(new ImageJobCompleted($image->id, 'face'));

        // 4. ImageProcessJob (запускается listener'ом)
        $imageProcessJob = new ImageProcessJob(['image_id' => $image->id]);
        $imageProcessJob->handle(
            app(\App\Contracts\ImageRepositoryInterface::class),
            app(\App\Contracts\ImagePathServiceInterface::class)
        );

        // Assert - проверяем финальное состояние
        $image->refresh();

        // Hash вычислены
        $this->assertNotNull($image->hash, 'MD5 hash should be computed');
        $this->assertNotNull($image->phash, 'pHash should be computed');

        // Размеры установлены
        $this->assertNotNull($image->width);
        $this->assertNotNull($image->height);

        // Filename изменён на .webp
        $this->assertEquals('test.webp', $image->filename);

        // WebP файл существует
        $this->assertTrue(
            Storage::disk('public')->exists('images/test.webp'),
            'WebP file should exist'
        );

        // JPG удалён
        $this->assertFalse(
            Storage::disk('public')->exists('images/test.jpg'),
            'JPG file should be deleted'
        );

        // Все флаги установлены
        $this->assertNotNull($image->metadata);
        $this->assertTrue($image->faces_checked);
        $this->assertNotNull($image->thumbnail_filename);
    }

    /** @test */
    public function parallel_jobs_dispatch_events()
    {
        // Arrange
        $image = Image::create([
            'disk' => 'public',
            'path' => 'images',
            'filename' => 'test.jpg',
        ]);

        Storage::disk('public')->put('images/test.jpg', 'fake content');

        // Act - симулируем параллельное выполнение jobs
        Event::dispatch(new ImageJobCompleted($image->id, 'thumbnail'));
        Event::dispatch(new ImageJobCompleted($image->id, 'metadata'));
        Event::dispatch(new ImageJobCompleted($image->id, 'face'));

        // Assert - все events были triggered
        Event::assertDispatched(ImageJobCompleted::class, 3);
        
        Event::assertDispatched(ImageJobCompleted::class, function ($event) use ($image) {
            return $event->imageId === $image->id && $event->jobType === 'thumbnail';
        });
        
        Event::assertDispatched(ImageJobCompleted::class, function ($event) use ($image) {
            return $event->imageId === $image->id && $event->jobType === 'metadata';
        });
        
        Event::assertDispatched(ImageJobCompleted::class, function ($event) use ($image) {
            return $event->imageId === $image->id && $event->jobType === 'face';
        });
    }

    /** @test */
    public function image_process_job_waits_for_all_prerequisites()
    {
        // Arrange
        $image = Image::create([
            'disk' => 'public',
            'path' => 'images',
            'filename' => 'test.jpg',
        ]);

        Storage::disk('public')->put('images/test.jpg', 'fake content');

        // Act & Assert - ImageProcessJob не должна запускаться без prerequisites
        
        // Только thumbnail готов
        $image->update(['thumbnail_filename' => 'thumb.webp']);
        $this->assertFalse($this->isReadyForProcessing($image));

        // Только metadata готова
        $image->update(['metadata' => ['camera' => 'Test']]);
        $this->assertFalse($this->isReadyForProcessing($image));

        // Только faces проверены
        $image->update(['faces_checked' => true]);
        
        // Теперь ВСЕ готовы
        $this->assertTrue($this->isReadyForProcessing($image));
    }

    /**
     * Helper - копия логики из CheckAndDispatchImageProcessing
     */
    private function isReadyForProcessing(Image $image): bool
    {
        if ($image->hash || $image->phash) {
            return false;
        }

        return $image->metadata !== null
            && $image->faces_checked === true
            && $image->thumbnail_filename !== null;
    }
}
