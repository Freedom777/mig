<?php

namespace Tests\Unit;

use App\Services\ImagePathService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImagePathServiceTest extends TestCase
{
    protected ImagePathService $pathService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pathService = new ImagePathService();

        // Fake storage для тестов
        Storage::fake('private');
    }

    // =========================================================================
    // getImagePathByObj()
    // =========================================================================

    /** @test */
    public function it_returns_full_path_for_image_object(): void
    {
        $image = $this->createTestImage([
            'disk' => 'private',
            'path' => 'images/2024',
            'filename' => 'photo.jpg',
        ]);

        $path = $this->pathService->getImagePathByObj($image);

        $this->assertStringContainsString('images/2024/photo.jpg', $path);
    }

    /** @test */
    public function it_handles_nested_paths(): void
    {
        $image = $this->createTestImage([
            'disk' => 'private',
            'path' => 'images/2024/vacation/italy',
            'filename' => 'photo.jpg',
        ]);

        $path = $this->pathService->getImagePathByObj($image);

        $this->assertStringContainsString('images/2024/vacation/italy/photo.jpg', $path);
    }

    // =========================================================================
    // getImagePathByParams()
    // =========================================================================

    /** @test */
    public function it_returns_full_path_by_params(): void
    {
        $path = $this->pathService->getImagePathByParams('private', 'images/test', 'photo.jpg');

        $this->assertStringContainsString('images/test/photo.jpg', $path);
    }

    // =========================================================================
    // getThumbnailSubdir()
    // =========================================================================

    /** @test */
    public function it_returns_thumbnail_subdir_with_default_format(): void
    {
        config(['image.thumbnails.dir_format' => '{width}x{height}']);

        $subdir = $this->pathService->getThumbnailSubdir(300, 200);

        $this->assertEquals('300x200', $subdir);
    }

    /** @test */
    public function it_returns_thumbnail_subdir_with_custom_format(): void
    {
        config(['image.thumbnails.dir_format' => 'thumb_{width}_{height}']);

        $subdir = $this->pathService->getThumbnailSubdir(400, 300);

        $this->assertEquals('thumb_400_300', $subdir);
    }

    // =========================================================================
    // getThumbnailFilename()
    // =========================================================================

    /** @test */
    public function it_generates_thumbnail_filename_with_default_postfix(): void
    {
        config(['image.thumbnails.postfix' => '_{method}_{width}x{height}']);

        $filename = $this->pathService->getThumbnailFilename('photo.jpg', 'cover', 300, 200);

        $this->assertEquals('photo_cover_300x200.jpg', $filename);
    }

    /** @test */
    public function it_generates_thumbnail_filename_with_custom_postfix(): void
    {
        config(['image.thumbnails.postfix' => '_thumb_{method}']);

        $filename = $this->pathService->getThumbnailFilename('image.png', 'scale', 150, 100);

        $this->assertEquals('image_thumb_scale.png', $filename);
    }

    /** @test */
    public function it_preserves_extension_in_thumbnail_filename(): void
    {
        config(['image.thumbnails.postfix' => '_{method}']);

        $this->assertEquals('photo_cover.jpg', $this->pathService->getThumbnailFilename('photo.jpg', 'cover', 300, 200));
        $this->assertEquals('image_scale.png', $this->pathService->getThumbnailFilename('image.png', 'scale', 300, 200));
        $this->assertEquals('pic_resize.gif', $this->pathService->getThumbnailFilename('pic.gif', 'resize', 300, 200));
    }

    /** @test */
    public function it_handles_filenames_with_multiple_dots(): void
    {
        config(['image.thumbnails.postfix' => '_{method}']);

        $filename = $this->pathService->getThumbnailFilename('my.photo.2024.jpg', 'cover', 300, 200);

        $this->assertEquals('my.photo.2024_cover.jpg', $filename);
    }

    // =========================================================================
    // getImageDebugSubdir()
    // =========================================================================

    /** @test */
    public function it_returns_debug_subdir_from_config(): void
    {
        config(['image.paths.debug_subdir' => 'debug']);

        $this->assertEquals('debug', $this->pathService->getImageDebugSubdir());
    }

    /** @test */
    public function it_returns_custom_debug_subdir(): void
    {
        config(['image.paths.debug_subdir' => 'face_debug']);

        $this->assertEquals('face_debug', $this->pathService->getImageDebugSubdir());
    }

    // =========================================================================
    // getDebugImagePath()
    // =========================================================================

    /** @test */
    public function it_returns_debug_image_path_when_debug_filename_set(): void
    {
        $image = $this->createTestImage([
            'disk' => 'private',
            'path' => 'images/test',
            'filename' => 'photo.jpg',
            'debug_filename' => 'photo_debug.jpg',
        ]);

        $path = $this->pathService->getDebugImagePath($image);

        $this->assertStringContainsString('images/test/debug/photo_debug.jpg', $path);
    }

    /** @test */
    public function it_returns_null_when_debug_filename_not_set(): void
    {
        $image = $this->createTestImage([
            'debug_filename' => null,
        ]);

        $path = $this->pathService->getDebugImagePath($image);

        $this->assertNull($path);
    }

    // =========================================================================
    // getDefaultThumbnailPath()
    // =========================================================================

    /** @test */
    public function it_returns_default_thumbnail_path(): void
    {
        $image = $this->createTestImage([
            'disk' => 'private',
            'path' => 'images/test',
            'filename' => 'photo.jpg',
            'thumbnail_path' => '300x200',
            'thumbnail_filename' => 'photo_cover_300x200.jpg',
        ]);

        $path = $this->pathService->getDefaultThumbnailPath($image);

        $this->assertStringContainsString('images/test/300x200/photo_cover_300x200.jpg', $path);
    }

    /** @test */
    public function it_returns_null_when_thumbnail_not_generated(): void
    {
        $image = $this->createTestImage([
            'thumbnail_path' => null,
            'thumbnail_filename' => null,
        ]);

        $path = $this->pathService->getDefaultThumbnailPath($image);

        $this->assertNull($path);
    }
}
