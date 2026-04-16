<?php

namespace Tests\Unit\Listeners;

use App\Events\ImageJobCompleted;
use App\Jobs\ImageProcessJob;
use App\Listeners\CheckAndDispatchImageProcessing;
use App\Models\Image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckAndDispatchImageProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function it_dispatches_image_process_job_when_all_jobs_completed()
    {
        // Arrange
        $image = Image::create([
            'disk' => 'public',
            'path' => 'images',
            'filename' => 'test.jpg',
            'metadata' => ['camera' => 'Test'],
            'faces_checked' => true,
            'thumbnail_filename' => 'test_thumb.webp',
            'hash' => null, // Еще не обработан
            'phash' => null,
        ]);

        $event = new ImageJobCompleted($image->id, 'face');
        $listener = new CheckAndDispatchImageProcessing();

        // Act
        $listener->handle($event);

        // Assert
        Queue::assertPushed(ImageProcessJob::class, function ($job) use ($image) {
            return $job->taskData['image_id'] === $image->id;
        });
    }

    /** @test */
    public function it_does_not_dispatch_when_metadata_missing()
    {
        // Arrange
        $image = Image::create([
            'disk' => 'public',
            'path' => 'images',
            'filename' => 'test.jpg',
            'metadata' => null, // Missing!
            'faces_checked' => true,
            'thumbnail_filename' => 'test_thumb.webp',
        ]);

        $event = new ImageJobCompleted($image->id, 'thumbnail');
        $listener = new CheckAndDispatchImageProcessing();

        // Act
        $listener->handle($event);

        // Assert
        Queue::assertNotPushed(ImageProcessJob::class);
    }

    /** @test */
    public function it_does_not_dispatch_when_faces_not_checked()
    {
        // Arrange
        $image = Image::create([
            'disk' => 'public',
            'path' => 'images',
            'filename' => 'test.jpg',
            'metadata' => ['camera' => 'Test'],
            'faces_checked' => false, // Not checked!
            'thumbnail_filename' => 'test_thumb.webp',
        ]);

        $event = new ImageJobCompleted($image->id, 'metadata');
        $listener = new CheckAndDispatchImageProcessing();

        // Act
        $listener->handle($event);

        // Assert
        Queue::assertNotPushed(ImageProcessJob::class);
    }

    /** @test */
    public function it_does_not_dispatch_when_thumbnail_missing()
    {
        // Arrange
        $image = Image::create([
            'disk' => 'public',
            'path' => 'images',
            'filename' => 'test.jpg',
            'metadata' => ['camera' => 'Test'],
            'faces_checked' => true,
            'thumbnail_filename' => null, // Missing!
        ]);

        $event = new ImageJobCompleted($image->id, 'face');
        $listener = new CheckAndDispatchImageProcessing();

        // Act
        $listener->handle($event);

        // Assert
        Queue::assertNotPushed(ImageProcessJob::class);
    }

    /** @test */
    public function it_does_not_dispatch_twice_if_already_processed()
    {
        // Arrange
        $image = Image::create([
            'disk' => 'public',
            'path' => 'images',
            'filename' => 'test.webp',
            'metadata' => ['camera' => 'Test'],
            'faces_checked' => true,
            'thumbnail_filename' => 'test_thumb.webp',
            'hash' => 'already_processed', // Already has hash!
            'phash' => 'already_processed',
        ]);

        $event = new ImageJobCompleted($image->id, 'face');
        $listener = new CheckAndDispatchImageProcessing();

        // Act
        $listener->handle($event);

        // Assert
        Queue::assertNotPushed(ImageProcessJob::class);
    }

    /** @test */
    public function it_handles_different_job_types()
    {
        // Arrange
        $image = Image::create([
            'disk' => 'public',
            'path' => 'images',
            'filename' => 'test.jpg',
            'metadata' => ['camera' => 'Test'],
            'faces_checked' => true,
            'thumbnail_filename' => 'test_thumb.webp',
        ]);

        $listener = new CheckAndDispatchImageProcessing();

        // Act & Assert - все job types должны работать
        $jobTypes = ['thumbnail', 'metadata', 'face'];
        
        foreach ($jobTypes as $jobType) {
            Queue::fake(); // Reset queue
            $event = new ImageJobCompleted($image->id, $jobType);
            $listener->handle($event);
            
            Queue::assertPushed(ImageProcessJob::class);
        }
    }

    /** @test */
    public function it_handles_non_existent_image_gracefully()
    {
        // Arrange
        $event = new ImageJobCompleted(99999, 'thumbnail'); // Non-existent ID
        $listener = new CheckAndDispatchImageProcessing();

        // Act
        $listener->handle($event);

        // Assert
        Queue::assertNotPushed(ImageProcessJob::class);
        // Should not throw exception
    }
}
