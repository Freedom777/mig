<?php

namespace Tests\Feature;

use App\Contracts\ImageQueueDispatcherInterface;
use App\Contracts\ImageServiceInterface;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // images:thumbnails
    // =========================================================================

    /** @test */
    public function thumbnails_command_queues_images_without_thumbnails(): void
    {
        // Создаём изображения без thumbnails
        $this->createTestImage(['thumbnail_path' => null]);
        $this->createTestImage(['thumbnail_path' => null]);
        $this->createTestImage(['thumbnail_path' => '300x200']); // С thumbnail

        $dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatchThumbnail')
            ->twice()
            ->andReturn('success');

        $this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);

        $this->artisan('images:thumbnails')
            ->expectsOutputToContain('Found 2 images')
            ->expectsOutputToContain('2 queued')
            ->assertExitCode(0);
    }

    /** @test */
    public function thumbnails_command_handles_empty_result(): void
    {
        // Все изображения с thumbnails
        $this->createTestImage(['thumbnail_path' => '300x200']);

        $dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
        $dispatcher->shouldNotReceive('dispatchThumbnail');

        $this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);

        $this->artisan('images:thumbnails')
            ->expectsOutputToContain('No images without thumbnails')
            ->assertExitCode(0);
    }

    /** @test */
    public function thumbnails_command_counts_skipped(): void
    {
        $this->createTestImage(['thumbnail_path' => null]);
        $this->createTestImage(['thumbnail_path' => null]);

        $dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatchThumbnail')
            ->once()
            ->andReturn('success');
        $dispatcher->shouldReceive('dispatchThumbnail')
            ->once()
            ->andReturn('exists'); // Уже в очереди

        $this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);

        $this->artisan('images:thumbnails')
            ->expectsOutputToContain('1 queued')
            ->expectsOutputToContain('1 skipped')
            ->assertExitCode(0);
    }

    // =========================================================================
    // images:metadatas
    // =========================================================================

    /** @test */
    public function metadatas_command_queues_images_without_metadata(): void
    {
        $this->createTestImage(['metadata' => null]);
        $this->createTestImage(['metadata' => null]);
        $this->createTestImage(['metadata' => ['test' => 'data']]);

        $dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatchMetadata')
            ->twice()
            ->andReturn('success');

        $this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);

        $this->artisan('images:metadatas')
            ->expectsOutputToContain('Found 2 images')
            ->assertExitCode(0);
    }

    /** @test */
    public function metadatas_command_handles_empty_result(): void
    {
        $this->createTestImage(['metadata' => ['has' => 'data']]);

        $dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
        $dispatcher->shouldNotReceive('dispatchMetadata');

        $this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);

        $this->artisan('images:metadatas')
            ->expectsOutputToContain('No images without metadata')
            ->assertExitCode(0);
    }

    // =========================================================================
    // images:faces
    // =========================================================================

    /** @test */
    public function faces_command_queues_unchecked_images(): void
    {
        $this->createTestImage(['faces_checked' => false]);
        $this->createTestImage(['faces_checked' => false]);
        $this->createTestImage(['faces_checked' => true]);

        $dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatchFace')
            ->twice()
            ->andReturn('success');

        $this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);

        $this->artisan('images:faces')
            ->expectsOutputToContain('Found 2 images')
            ->assertExitCode(0);
    }

    /** @test */
    public function faces_command_handles_empty_result(): void
    {
        $this->createTestImage(['faces_checked' => true]);

        $dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
        $dispatcher->shouldNotReceive('dispatchFace');

        $this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);

        $this->artisan('images:faces')
            ->expectsOutputToContain('No images awaiting face detection')
            ->assertExitCode(0);
    }

    // =========================================================================
    // images:geolocations
    // =========================================================================

    /** @test */
    public function geolocations_command_queues_images_with_gps(): void
    {
        // Изображение с GPS координатами без geolocation point
        $this->createTestImage([
            'metadata' => [
                'GPSLatitude' => 55.7558,
                'GPSLongitude' => 37.6173,
            ],
            'image_geolocation_point_id' => null,
        ]);

        // Изображение без GPS
        $this->createTestImage([
            'metadata' => ['Make' => 'Canon'],
            'image_geolocation_point_id' => null,
        ]);

        // Изображение с GPS но уже с point
        $this->createTestImage([
            'metadata' => [
                'GPSLatitude' => 40.7128,
                'GPSLongitude' => -74.0060,
            ],
            'image_geolocation_point_id' => 1,
        ]);

        $dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatchGeolocation')
            ->once()
            ->andReturn('success');

        $this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);

        $this->artisan('images:geolocations')
            ->expectsOutputToContain('Found 1 images')
            ->assertExitCode(0);
    }

    /** @test */
    public function geolocations_command_handles_empty_result(): void
    {
        $this->createTestImage([
            'metadata' => ['Make' => 'Canon'], // Без GPS
            'image_geolocation_point_id' => null,
        ]);

        $dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
        $dispatcher->shouldNotReceive('dispatchGeolocation');

        $this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);

        $this->artisan('images:geolocations')
            ->expectsOutputToContain('No images with GPS data')
            ->assertExitCode(0);
    }

    // =========================================================================
    // Error handling
    // =========================================================================

    /** @test */
    public function command_returns_failure_on_errors(): void
    {
        $this->createTestImage(['thumbnail_path' => null]);

        $dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatchThumbnail')
            ->once()
            ->andReturn('error');

        $this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);

        $this->artisan('images:thumbnails')
            ->expectsOutputToContain('1 errors')
            ->assertExitCode(1);
    }

    /** @test */
    public function command_handles_dispatch_exceptions(): void
    {
        $this->createTestImage(['thumbnail_path' => null]);

        $dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatchThumbnail')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);

        $this->artisan('images:thumbnails')
            ->expectsOutputToContain('Failed for image')
            ->expectsOutputToContain('1 errors')
            ->assertExitCode(1);
    }
}
