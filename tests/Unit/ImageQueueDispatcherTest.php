<?php

namespace Tests\Unit;

use App\Contracts\ImagePathServiceInterface;
use App\Jobs\BaseProcessJob;
use App\Services\ImageQueueDispatcher;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ImageQueueDispatcherTest extends TestCase
{
    protected MockInterface $pathService;
    protected ImageQueueDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pathService = Mockery::mock(ImagePathServiceInterface::class);
        $this->dispatcher = new ImageQueueDispatcher($this->pathService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // getMode() / setMode()
    // =========================================================================

    /** @test */
    public function it_returns_mode_from_config(): void
    {
        config(['image.processing.mode' => 'sync']);

        $this->assertEquals('sync', $this->dispatcher->getMode());
    }

    /** @test */
    public function it_allows_mode_override(): void
    {
        config(['image.processing.mode' => 'queue']);

        $this->dispatcher->setMode('sync');

        $this->assertEquals('sync', $this->dispatcher->getMode());
    }

    /** @test */
    public function it_throws_exception_for_invalid_mode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid processing mode');

        $this->dispatcher->setMode('invalid_mode');
    }

    /** @test */
    public function setMode_accepts_valid_modes(): void
    {
        foreach (['queue', 'sync', 'disabled'] as $mode) {
            $this->dispatcher->setMode($mode);
            $this->assertEquals($mode, $this->dispatcher->getMode());
        }
    }

    // =========================================================================
    // isDryRun() / setDryRun()
    // =========================================================================

    /** @test */
    public function it_returns_dry_run_from_config(): void
    {
        config(['image.processing.dry_run' => true]);

        $this->assertTrue($this->dispatcher->isDryRun());
    }

    /** @test */
    public function it_allows_dry_run_override(): void
    {
        config(['image.processing.dry_run' => false]);

        $this->dispatcher->setDryRun(true);

        $this->assertTrue($this->dispatcher->isDryRun());
    }

    // =========================================================================
    // isDebug() / setDebug()
    // =========================================================================

    /** @test */
    public function it_returns_debug_from_config(): void
    {
        config(['image.processing.debug' => true]);

        $this->assertTrue($this->dispatcher->isDebug());
    }

    /** @test */
    public function it_allows_debug_override(): void
    {
        config(['image.processing.debug' => false]);

        $this->dispatcher->setDebug(true);

        $this->assertTrue($this->dispatcher->isDebug());
    }

    // =========================================================================
    // Fluent interface (chaining)
    // =========================================================================

    /** @test */
    public function it_supports_method_chaining(): void
    {
        $result = $this->dispatcher
            ->setMode('sync')
            ->setDryRun(true)
            ->setDebug(true);

        $this->assertSame($this->dispatcher, $result);
        $this->assertEquals('sync', $this->dispatcher->getMode());
        $this->assertTrue($this->dispatcher->isDryRun());
        $this->assertTrue($this->dispatcher->isDebug());
    }

    // =========================================================================
    // Disabled mode
    // =========================================================================

    /** @test */
    public function it_returns_skipped_for_all_jobs_in_disabled_mode(): void
    {
        $this->dispatcher->setMode('disabled');
        $image = $this->createTestImage();

        $result = $this->dispatcher->dispatchAll($image);

        $this->assertEquals('skipped', $result['image']);
        $this->assertEquals('skipped', $result['thumbnail']);
        $this->assertEquals('skipped', $result['metadata']);
        $this->assertEquals('skipped', $result['face']);
    }

    /** @test */
    public function it_returns_skipped_for_single_dispatch_in_disabled_mode(): void
    {
        $this->dispatcher->setMode('disabled');
        $image = $this->createTestImage();

        $this->assertEquals('skipped', $this->dispatcher->dispatchImageProcess($image));
        $this->assertEquals('skipped', $this->dispatcher->dispatchFace($image));
        $this->assertEquals('skipped', $this->dispatcher->dispatchMetadata($image));
        $this->assertEquals('skipped', $this->dispatcher->dispatchGeolocation($image));
    }

    /** @test */
    public function it_returns_skipped_for_thumbnail_in_disabled_mode(): void
    {
        $this->dispatcher->setMode('disabled');
        $image = $this->createTestImage();

        // Не должен вызывать pathService
        $this->pathService->shouldNotReceive('getThumbnailSubdir');
        $this->pathService->shouldNotReceive('getThumbnailFilename');

        $this->assertEquals('skipped', $this->dispatcher->dispatchThumbnail($image));
    }

    // =========================================================================
    // Dry-run mode
    // =========================================================================

    /** @test */
    public function it_returns_dryrun_status_and_logs_for_all_jobs(): void
    {
        $this->dispatcher->setDryRun(true);
        $image = $this->createTestImage();

        $this->pathService->shouldReceive('getThumbnailSubdir')->andReturn('300x200');
        $this->pathService->shouldReceive('getThumbnailFilename')->andReturn('test.jpg');

        Log::shouldReceive('info')->atLeast()->times(5); // dispatch + summary

        $result = $this->dispatcher->dispatchAll($image);

        $this->assertEquals('dry-run', $result['image']);
        $this->assertEquals('dry-run', $result['thumbnail']);
        $this->assertEquals('dry-run', $result['metadata']);
        $this->assertEquals('dry-run', $result['face']);
    }

    /** @test */
    public function it_logs_what_would_be_queued_in_dryrun(): void
    {
        $this->dispatcher->setDryRun(true);
        $image = $this->createTestImage();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use ($image) {
                return str_contains($message, 'DRY-RUN')
                    && str_contains($message, 'queue')
                    && str_contains($message, 'Image')
                    && $context['image_id'] === $image->id;
            });

        Log::shouldReceive('info')->atLeast()->once();

        $this->dispatcher->dispatchImageProcess($image);
    }

    /** @test */
    public function it_logs_what_would_be_executed_in_sync_dryrun(): void
    {
        $this->dispatcher->setMode('sync');
        $this->dispatcher->setDryRun(true);
        $image = $this->createTestImage();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'DRY-RUN')
                    && str_contains($message, 'execute');
            });

        Log::shouldReceive('info')->atLeast()->once();

        $this->dispatcher->dispatchImageProcess($image);
    }

    // =========================================================================
    // Debug mode
    // =========================================================================

    /** @test */
    public function it_logs_debug_info_when_debug_enabled(): void
    {
        $this->dispatcher->setDebug(true);
        $this->dispatcher->setMode('disabled');
        $image = $this->createTestImage();

        Log::shouldReceive('debug')
            ->atLeast()
            ->once()
            ->withArgs(function ($message, $context) use ($image) {
                return isset($context['image_id'])
                    && $context['image_id'] === $image->id
                    && isset($context['mode']);
            });

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->dispatcher->dispatchImageProcess($image);
    }

    /** @test */
    public function it_logs_debug_for_dispatchAll_start(): void
    {
        $this->dispatcher->setDebug(true);
        $this->dispatcher->setMode('disabled');
        $image = $this->createTestImage();

        Log::shouldReceive('debug')
            ->atLeast()
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'dispatchAll');
            });

        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->dispatcher->dispatchAll($image);
    }

    // =========================================================================
    // dispatchThumbnail() specifics
    // =========================================================================

    /** @test */
    public function it_uses_path_service_for_thumbnail_params(): void
    {
        $this->dispatcher->setDryRun(true);
        $image = $this->createTestImage(['filename' => 'photo.jpg']);

        $this->pathService
            ->shouldReceive('getThumbnailSubdir')
            ->once()
            ->with(300, 200)
            ->andReturn('300x200');

        $this->pathService
            ->shouldReceive('getThumbnailFilename')
            ->once()
            ->with('photo.jpg', 'cover', 300, 200)
            ->andReturn('photo_cover_300x200.jpg');

        Log::shouldReceive('info')->atLeast()->once();

        $result = $this->dispatcher->dispatchThumbnail($image);

        $this->assertEquals('dry-run', $result);
    }

    /** @test */
    public function it_uses_thumbnail_config_values(): void
    {
        config([
            'image.thumbnails.width' => 400,
            'image.thumbnails.height' => 300,
            'image.thumbnails.method' => 'contain',
        ]);

        $this->dispatcher->setDryRun(true);
        $image = $this->createTestImage(['filename' => 'test.jpg']);

        $this->pathService
            ->shouldReceive('getThumbnailSubdir')
            ->with(400, 300)
            ->andReturn('400x300');

        $this->pathService
            ->shouldReceive('getThumbnailFilename')
            ->with('test.jpg', 'contain', 400, 300)
            ->andReturn('test_contain_400x300.jpg');

        Log::shouldReceive('info')->atLeast()->once();

        $this->dispatcher->dispatchThumbnail($image);
    }

    // =========================================================================
    // dispatchAll() structure
    // =========================================================================

    /** @test */
    public function it_returns_array_with_all_job_statuses(): void
    {
        $this->dispatcher->setMode('disabled');
        $image = $this->createTestImage();

        $result = $this->dispatcher->dispatchAll($image);

        $this->assertArrayHasKey('image', $result);
        $this->assertArrayHasKey('thumbnail', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('face', $result);
        $this->assertCount(4, $result);
    }

    /** @test */
    public function it_logs_summary_after_dispatch_all(): void
    {
        $this->dispatcher->setMode('disabled');
        $image = $this->createTestImage();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'summary')
                    && isset($context['statuses'])
                    && isset($context['mode']);
            });

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $this->dispatcher->dispatchAll($image);
    }

    // =========================================================================
    // dispatchGeolocation()
    // =========================================================================

    /** @test */
    public function it_dispatches_geolocation_job(): void
    {
        $this->dispatcher->setDryRun(true);
        $image = $this->createTestImage();

        Log::shouldReceive('info')->atLeast()->once();

        $result = $this->dispatcher->dispatchGeolocation($image);

        $this->assertEquals('dry-run', $result);
    }
}
