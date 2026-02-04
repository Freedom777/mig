<?php

namespace Tests\Unit;

use App\Contracts\ImageQueueDispatcherInterface;
use App\Contracts\ImageRepositoryInterface;
use App\Models\Image;
use App\Services\ImageService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ImageServiceTest extends TestCase
{
    protected MockInterface $repository;
    protected MockInterface $dispatcher;
    protected ImageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ImageRepositoryInterface::class);
        $this->dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);

        $this->service = new ImageService($this->repository, $this->dispatcher);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // processNewUpload() - успешные сценарии
    // =========================================================================

    /** @test */
    public function it_creates_image_and_dispatches_all_jobs(): void
    {
        $disk = 'private';
        $path = 'images/test';
        $filename = 'photo.jpg';

        $preparedData = [
            'source_disk' => $disk,
            'source_path' => $path,
            'source_filename' => $filename,
            'size' => 1024000,
        ];

        $image = $this->createTestImage([
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
        ]);

        $this->repository
            ->shouldReceive('prepareImageData')
            ->once()
            ->with($disk, $path, $filename)
            ->andReturn($preparedData);

        $this->repository
            ->shouldReceive('updateOrCreate')
            ->once()
            ->with($preparedData)
            ->andReturn($image);

        $this->dispatcher
            ->shouldReceive('dispatchAll')
            ->once()
            ->with(Mockery::on(fn($arg) => $arg->id === $image->id))
            ->andReturn([
                'image' => 'success',
                'thumbnail' => 'success',
                'metadata' => 'success',
                'face' => 'success',
            ]);

        $result = $this->service->processNewUpload($disk, $path, $filename);

        $this->assertTrue($result['success']);
        $this->assertEquals($image->id, $result['image']->id);
        $this->assertEquals('Image uploaded and processing started', $result['message']);
        $this->assertEquals('success', $result['queue_statuses']['image']);
        $this->assertEquals('success', $result['queue_statuses']['thumbnail']);
    }

    /** @test */
    public function it_skips_existing_image_when_flag_is_set(): void
    {
        $disk = 'private';
        $path = 'images/test';
        $filename = 'existing.jpg';

        $this->repository
            ->shouldReceive('exists')
            ->once()
            ->with($disk, $path, $filename)
            ->andReturn(true);

        $this->repository->shouldNotReceive('prepareImageData');
        $this->repository->shouldNotReceive('updateOrCreate');
        $this->dispatcher->shouldNotReceive('dispatchAll');

        $result = $this->service->processNewUpload($disk, $path, $filename, skipIfExists: true);

        $this->assertFalse($result['success']);
        $this->assertNull($result['image']);
        $this->assertStringContainsString('already exists', $result['message']);
    }

    /** @test */
    public function it_processes_new_image_when_not_exists_and_skip_flag_set(): void
    {
        $disk = 'private';
        $path = 'images/test';
        $filename = 'new.jpg';

        $preparedData = ['source_disk' => $disk, 'source_path' => $path, 'source_filename' => $filename];
        $image = $this->createTestImage(['filename' => $filename]);

        $this->repository->shouldReceive('exists')->once()->andReturn(false);
        $this->repository->shouldReceive('prepareImageData')->once()->andReturn($preparedData);
        $this->repository->shouldReceive('updateOrCreate')->once()->andReturn($image);
        $this->dispatcher->shouldReceive('dispatchAll')->once()->andReturn([
            'image' => 'success', 'thumbnail' => 'success', 'metadata' => 'success', 'face' => 'success'
        ]);

        $result = $this->service->processNewUpload($disk, $path, $filename, skipIfExists: true);

        $this->assertTrue($result['success']);
    }

    // =========================================================================
    // processNewUpload() - ошибки
    // =========================================================================

    /** @test */
    public function it_returns_failure_when_insert_fails(): void
    {
        $disk = 'private';
        $path = 'images/test';
        $filename = 'photo.jpg';

        $this->repository
            ->shouldReceive('prepareImageData')
            ->once()
            ->andReturn(['source_disk' => $disk]);

        $this->repository
            ->shouldReceive('updateOrCreate')
            ->once()
            ->andReturn(null);

        $this->dispatcher->shouldNotReceive('dispatchAll');

        $result = $this->service->processNewUpload($disk, $path, $filename);

        $this->assertFalse($result['success']);
        $this->assertNull($result['image']);
        $this->assertStringContainsString('Failed to insert', $result['message']);
    }

    // =========================================================================
    // queueForProcessing()
    // =========================================================================

    /** @test */
    public function it_queues_existing_image_for_processing(): void
    {
        $image = $this->createTestImage();

        $this->dispatcher
            ->shouldReceive('dispatchImageProcess')
            ->once()
            ->with(Mockery::on(fn($arg) => $arg->id === $image->id))
            ->andReturn('success');

        $result = $this->service->queueForProcessing($image);

        $this->assertEquals($image->id, $result['image_id']);
        $this->assertEquals('success', $result['status']);
    }

    /** @test */
    public function it_returns_exists_when_already_in_queue(): void
    {
        $image = $this->createTestImage();

        $this->dispatcher
            ->shouldReceive('dispatchImageProcess')
            ->once()
            ->andReturn('exists');

        $result = $this->service->queueForProcessing($image);

        $this->assertEquals('exists', $result['status']);
    }
}
