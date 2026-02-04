<?php

namespace Tests\Unit;

use App\Models\Image;
use Tests\TestCase;

class ImageModelTest extends TestCase
{
    // =========================================================================
    // Attribute casting
    // =========================================================================

    /** @test */
    public function it_casts_metadata_to_array(): void
    {
        $image = $this->createTestImage([
            'metadata' => ['Make' => 'Canon', 'Model' => 'EOS R5'],
        ]);

        $this->assertIsArray($image->metadata);
        $this->assertEquals('Canon', $image->metadata['Make']);
    }

    /** @test */
    public function it_handles_null_metadata(): void
    {
        $image = $this->createTestImage(['metadata' => null]);

        $this->assertNull($image->metadata);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /** @test */
    public function it_filters_by_status(): void
    {
        $this->createTestImage(['status' => 'process']);
        $this->createTestImage(['status' => 'process']);
        $this->createTestImage(['status' => 'ok']);

        $processCount = Image::where('status', 'process')->count();
        $okCount = Image::where('status', 'ok')->count();

        $this->assertEquals(2, $processCount);
        $this->assertEquals(1, $okCount);
    }

    /** @test */
    public function it_filters_images_without_thumbnail(): void
    {
        $this->createTestImage(['thumbnail_path' => null]);
        $this->createTestImage(['thumbnail_path' => null]);
        $this->createTestImage(['thumbnail_path' => '300x200']);

        $count = Image::whereNull('thumbnail_path')->count();

        $this->assertEquals(2, $count);
    }

    /** @test */
    public function it_filters_images_without_metadata(): void
    {
        $this->createTestImage(['metadata' => null]);
        $this->createTestImage(['metadata' => ['test' => 'data']]);

        $count = Image::whereNull('metadata')->count();

        $this->assertEquals(1, $count);
    }

    /** @test */
    public function it_filters_images_not_checked_for_faces(): void
    {
        $this->createTestImage(['faces_checked' => false]);
        $this->createTestImage(['faces_checked' => false]);
        $this->createTestImage(['faces_checked' => true]);

        $count = Image::where('faces_checked', false)->count();

        $this->assertEquals(2, $count);
    }

    // =========================================================================
    // Relationships (basic)
    // =========================================================================

    /** @test */
    public function it_can_have_parent_image(): void
    {
        $parent = $this->createTestImage(['filename' => 'original.jpg']);
        $duplicate = $this->createTestImage([
            'filename' => 'duplicate.jpg',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $duplicate->parent_id);
    }

    // =========================================================================
    // Unique constraint (index, not unique constraint)
    // =========================================================================

    /** @test */
    public function it_allows_same_filename_in_different_paths(): void
    {
        $this->createTestImage([
            'disk' => 'private',
            'path' => 'images/path1',
            'filename' => 'photo.jpg',
        ]);

        $image2 = $this->createTestImage([
            'disk' => 'private',
            'path' => 'images/path2',
            'filename' => 'photo.jpg',
        ]);

        $this->assertNotNull($image2->id);
        $this->assertEquals(2, Image::count());
    }

    // =========================================================================
    // Hard delete (Image не использует SoftDeletes)
    // =========================================================================

    /** @test */
    public function it_hard_deletes(): void
    {
        $image = $this->createTestImage();
        $id = $image->id;

        $image->delete();

        $this->assertNull(Image::find($id));
        $this->assertEquals(0, Image::count());
    }
}
