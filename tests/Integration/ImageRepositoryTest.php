<?php

namespace Tests\Integration;

use App\Models\Image;
use App\Repositories\ImageRepository;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Интеграционные тесты ImageRepository
 * 
 * ВАЖНО:
 * 1. findSimilarByPhash() требует MySQL (BIT_COUNT, XOR)
 * 2. phash = 8 байт = 16 hex символов
 * 3. updateOrCreate() требует реальный файл для prepareImageData()
 */
class ImageRepositoryTest extends TestCase
{
    protected ImageRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->repository = app(ImageRepository::class);
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

        $this->assertFalse($this->repository->exists('private', 'images/other', 'photo.jpg'));
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
    // updateOrCreate() — требует реальный файл
    // =========================================================================

    /** @test */
    public function it_creates_new_image(): void
    {
        Storage::disk('private')->put('images/new/newfile.jpg', 'fake image content');

        $data = $this->repository->prepareImageData('private', 'images/new', 'newfile.jpg');
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

        Storage::disk('private')->put('images/test/update.jpg', str_repeat('x', 2000));

        $data = $this->repository->prepareImageData('private', 'images/test', 'update.jpg');
        $image = $this->repository->updateOrCreate($data);

        $this->assertNotNull($image);
        $this->assertEquals($existing->id, $image->id);
        $this->assertEquals(2000, $image->fresh()->size);
    }

    /** @test */
    public function it_maps_source_fields_correctly(): void
    {
        Storage::disk('private')->put('images/mapped/mapped.jpg', 'content');

        $data = $this->repository->prepareImageData('private', 'images/mapped', 'mapped.jpg');
        $image = $this->repository->updateOrCreate($data);

        $this->assertNotNull($image);
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
        Storage::disk('private')->put('images/test/sample.jpg', 'fake image content');

        $data = $this->repository->prepareImageData('private', 'images/test', 'sample.jpg');

        $this->assertEquals('private', $data['source_disk']);
        $this->assertEquals('images/test', $data['source_path']);
        $this->assertEquals('sample.jpg', $data['source_filename']);
        $this->assertArrayHasKey('size', $data);
        $this->assertGreaterThan(0, $data['size']);
    }

    // =========================================================================
    // findSimilarByPhash() - MySQL ONLY
    // =========================================================================

    /**
     * @test
     * @group mysql
     */
    public function it_finds_similar_image_by_phash(): void
    {
        $this->skipIfNotMysql('findSimilarByPhash requires MySQL BIT_COUNT/XOR');

        $phashHex = '0123456789abcdef';

        $existing = $this->createTestImage(['phash' => $phashHex]);

        $foundId = $this->repository->findSimilarByPhash($phashHex, 5);

        $this->assertEquals($existing->id, $foundId);
    }

    /**
     * @test
     * @group mysql
     */
    public function it_returns_null_when_no_similar_phash(): void
    {
        $this->skipIfNotMysql('findSimilarByPhash requires MySQL BIT_COUNT/XOR');

        $this->createTestImage(['phash' => '0000000000000000']);

        $foundId = $this->repository->findSimilarByPhash('ffffffffffffffff', 5);

        $this->assertNull($foundId);
    }

    // =========================================================================
    // Hard deletes
    // =========================================================================

    /** @test */
    public function it_does_not_find_deleted_images(): void
    {
        $image = $this->createTestImage([
            'disk' => 'private',
            'path' => 'images/test',
            'filename' => 'deleted.jpg',
        ]);

        $image->delete();

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
