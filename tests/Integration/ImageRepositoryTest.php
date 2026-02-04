<?php

namespace Tests\Integration;

use App\Models\Image;
use App\Repositories\ImageRepository;
use App\Services\ImagePathService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageRepositoryTest extends TestCase
{
    protected ImageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $pathService = new ImagePathService();
        $this->repository = new ImageRepository($pathService);
    }

    // =========================================================================
    // exists()
    // =========================================================================

    /** @test */
    public function it_returns_true_when_image_exists(): void
    {
        $this->createTestImage([
            'disk' => 'private',
            'path' => 'images/test',
            'filename' => 'existing.jpg',
        ]);

        $exists = $this->repository->exists('private', 'images/test', 'existing.jpg');

        $this->assertTrue($exists);
    }

    /** @test */
    public function it_returns_false_when_image_not_exists(): void
    {
        $exists = $this->repository->exists('private', 'images/test', 'nonexistent.jpg');

        $this->assertFalse($exists);
    }

    /** @test */
    public function it_checks_exact_disk_path_filename_combination(): void
    {
        $this->createTestImage([
            'disk' => 'private',
            'path' => 'images/test',
            'filename' => 'photo.jpg',
        ]);

        // Same filename, different path
        $this->assertFalse($this->repository->exists('private', 'images/other', 'photo.jpg'));

        // Same path/filename, different disk
        $this->assertFalse($this->repository->exists('public', 'images/test', 'photo.jpg'));
    }

    // =========================================================================
    // find() / findOrFail()
    // =========================================================================

    /** @test */
    public function it_finds_image_by_id(): void
    {
        $image = $this->createTestImage(['filename' => 'findme.jpg']);

        $found = $this->repository->find($image->id);

        $this->assertNotNull($found);
        $this->assertEquals($image->id, $found->id);
        $this->assertEquals('findme.jpg', $found->filename);
    }

    /** @test */
    public function it_returns_null_when_image_not_found(): void
    {
        $found = $this->repository->find(99999);

        $this->assertNull($found);
    }

    /** @test */
    public function it_throws_exception_when_image_not_found_with_findOrFail(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->repository->findOrFail(99999);
    }

    // =========================================================================
    // updateOrCreate()
    // =========================================================================

    /** @test */
    public function it_creates_new_image(): void
    {
        $data = [
            'source_disk' => 'private',
            'source_path' => 'images/new',
            'source_filename' => 'newfile.jpg',
            'size' => 2048000,
        ];

        $image = $this->repository->updateOrCreate($data);

        $this->assertNotNull($image);
        $this->assertDatabaseHas('images', [
            'disk' => 'private',
            'path' => 'images/new',
            'filename' => 'newfile.jpg',
        ]);
    }

    /** @test */
    public function it_updates_existing_image(): void
    {
        $existing = $this->createTestImage([
            'disk' => 'private',
            'path' => 'images/test',
            'filename' => 'update.jpg',
            'size' => 1000,
        ]);

        $data = [
            'source_disk' => 'private',
            'source_path' => 'images/test',
            'source_filename' => 'update.jpg',
            'size' => 2000,
        ];

        $image = $this->repository->updateOrCreate($data);

        $this->assertEquals($existing->id, $image->id);
        $this->assertEquals(2000, $image->fresh()->size);
    }

    /** @test */
    public function it_maps_source_fields_correctly(): void
    {
        $data = [
            'source_disk' => 'private',
            'source_path' => 'images/mapped',
            'source_filename' => 'mapped.jpg',
            'size' => 1024,
        ];

        $image = $this->repository->updateOrCreate($data);

        $this->assertEquals('private', $image->disk);
        $this->assertEquals('images/mapped', $image->path);
        $this->assertEquals('mapped.jpg', $image->filename);
    }

    // =========================================================================
    // prepareImageData()
    // =========================================================================

    /** @test */
    public function it_prepares_image_data_from_file(): void
    {
        // Создаём фейковый файл
        Storage::disk('private')->put('images/test/sample.jpg', 'fake image content');

        $data = $this->repository->prepareImageData('private', 'images/test', 'sample.jpg');

        $this->assertEquals('private', $data['source_disk']);
        $this->assertEquals('images/test', $data['source_path']);
        $this->assertEquals('sample.jpg', $data['source_filename']);
        $this->assertArrayHasKey('size', $data);
        $this->assertGreaterThan(0, $data['size']);
    }

    // =========================================================================
    // findSimilarByPhash()
    // =========================================================================

    /** @test */
    public function it_finds_similar_image_by_phash(): void
    {
        // Создаём изображение с известным phash
        $existing = $this->createTestImage([
            'phash' => hex2bin('0123456789abcdef'),
        ]);

        // Ищем с тем же phash (distance = 0)
        $foundId = $this->repository->findSimilarByPhash('0123456789abcdef', 5);

        $this->assertEquals($existing->id, $foundId);
    }

    /** @test */
    public function it_returns_null_when_no_similar_phash(): void
    {
        $this->createTestImage([
            'phash' => hex2bin('0000000000000000'),
        ]);

        // Совсем другой phash
        $foundId = $this->repository->findSimilarByPhash('ffffffffffffffff', 5);

        $this->assertNull($foundId);
    }

    /** @test */
    public function it_excludes_images_without_phash(): void
    {
        $this->createTestImage(['phash' => null]);

        $foundId = $this->repository->findSimilarByPhash('0123456789abcdef', 10);

        $this->assertNull($foundId);
    }

    // =========================================================================
    // Hard deletes (Image не использует SoftDeletes)
    // =========================================================================

    /** @test */
    public function it_does_not_find_deleted_images(): void
    {
        $image = $this->createTestImage([
            'disk' => 'private',
            'path' => 'images/test',
            'filename' => 'deleted.jpg',
        ]);

        $image->delete(); // Hard delete

        $exists = $this->repository->exists('private', 'images/test', 'deleted.jpg');

        $this->assertFalse($exists);
    }

    /** @test */
    public function find_does_not_return_deleted(): void
    {
        $image = $this->createTestImage();
        $id = $image->id;

        $image->delete();

        $found = $this->repository->find($id);

        $this->assertNull($found);
    }
}
