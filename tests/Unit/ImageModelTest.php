<?php

namespace Tests\Unit;

use App\Models\Image;
use Tests\TestCase;

class ImageModelTest extends TestCase
{
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

    /** @test */
    public function it_converts_hash_hex_to_binary_and_back(): void
    {
        $hexHash = 'd41d8cd98f00b204e9800998ecf8427e';
        
        $image = $this->createTestImage();
        $image->hash = $hexHash;
        $image->save();

        $image->refresh();
        $this->assertEquals($hexHash, $image->hash);
    }

    /** @test */
    public function it_handles_null_hash(): void
    {
        $image = $this->createTestImage();
        $this->assertNull($image->hash);
    }

    /** @test */
    public function it_converts_phash_hex_to_binary_and_back(): void
    {
        $hexPhash = '0123456789abcdef';
        
        $image = $this->createTestImage();
        $image->phash = $hexPhash;
        $image->save();

        $image->refresh();
        $this->assertEquals($hexPhash, $image->phash);
    }

    /** @test */
    public function it_filters_by_status(): void
    {
        $this->createTestImage(['status' => 'process']);
        $this->createTestImage(['status' => 'process']);
        $this->createTestImage(['status' => 'ok']);

        $this->assertEquals(2, Image::where('status', 'process')->count());
        $this->assertEquals(1, Image::where('status', 'ok')->count());
    }

    /** @test */
    public function it_filters_images_without_thumbnail(): void
    {
        $this->createTestImage(['thumbnail_path' => null]);
        $this->createTestImage(['thumbnail_path' => null]);
        $this->createTestImage(['thumbnail_path' => '300x200']);

        $this->assertEquals(2, Image::whereNull('thumbnail_path')->count());
    }

    /** @test */
    public function it_filters_images_without_metadata(): void
    {
        $this->createTestImage(['metadata' => null]);
        $this->createTestImage(['metadata' => ['test' => 'data']]);

        $this->assertEquals(1, Image::whereNull('metadata')->count());
    }

    /** @test */
    public function it_filters_images_not_checked_for_faces(): void
    {
        $this->createTestImage(['faces_checked' => false]);
        $this->createTestImage(['faces_checked' => false]);
        $this->createTestImage(['faces_checked' => true]);

        $this->assertEquals(2, Image::where('faces_checked', false)->count());
    }

    /** @test */
    public function it_can_have_parent_image(): void
    {
        $parent = $this->createTestImage();
        $duplicate = $this->createTestImage(['parent_id' => $parent->id]);

        $this->assertEquals($parent->id, $duplicate->parent_id);
        $this->assertEquals($parent->id, $duplicate->parent->id);
    }

    /** @test */
    public function it_can_have_children(): void
    {
        $parent = $this->createTestImage();
        $this->createTestImage(['parent_id' => $parent->id]);
        $this->createTestImage(['parent_id' => $parent->id]);

        $this->assertEquals(2, $parent->children()->count());
    }

    /** @test */
    public function it_finds_previous_image(): void
    {
        $image1 = $this->createTestImage();
        $image2 = $this->createTestImage();
        $image3 = $this->createTestImage();

        $previous = Image::previous($image3->id);

        $this->assertEquals($image2->id, $previous->id);
    }

    /** @test */
    public function it_finds_next_image(): void
    {
        $image1 = $this->createTestImage();
        $image2 = $this->createTestImage();
        $image3 = $this->createTestImage();

        $next = Image::next($image1->id);

        $this->assertEquals($image2->id, $next->id);
    }

    /** @test */
    public function it_allows_same_filename_in_different_paths(): void
    {
        $this->createTestImage([
            'path' => 'images/path1',
            'filename' => 'photo.jpg',
        ]);

        $image2 = $this->createTestImage([
            'path' => 'images/path2',
            'filename' => 'photo.jpg',
        ]);

        $this->assertNotNull($image2->id);
        $this->assertEquals(2, Image::count());
    }

    /** @test */
    public function it_hard_deletes(): void
    {
        $image = $this->createTestImage();
        $id = $image->id;

        $image->delete();

        $this->assertNull(Image::find($id));
    }
}
