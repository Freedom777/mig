<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ImageProcessJob;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;

class ImageProcessJobTest extends JobTestCase
{
    /** @test */
    public function it_computes_md5_hash_from_jpg()
    {
        // Arrange
        $image = $this->createTestImage();
        $this->setImageReadyForProcessing($image);
        
        // Act
        $job = new ImageProcessJob(['image_id' => $image->id]);
        $job->handle(
            app(\App\Contracts\ImageRepositoryInterface::class),
            app(\App\Contracts\ImagePathServiceInterface::class)
        );
        
        // Assert
        $image->refresh();
        $this->assertNotNull($image->hash);
        $this->assertEquals(16, strlen($image->hash)); // MD5 = 16 bytes
    }

    /** @test */
    public function it_computes_phash_from_jpg()
    {
        // Arrange
        $image = $this->createTestImage();
        $this->setImageReadyForProcessing($image);
        
        // Act
        $job = new ImageProcessJob(['image_id' => $image->id]);
        $job->handle(
            app(\App\Contracts\ImageRepositoryInterface::class),
            app(\App\Contracts\ImagePathServiceInterface::class)
        );
        
        // Assert
        $image->refresh();
        $this->assertNotNull($image->phash);
        $this->assertEquals(8, strlen($image->phash)); // pHash = 8 bytes
    }

    /** @test */
    public function it_computes_image_dimensions()
    {
        // Arrange
        $image = $this->createTestImage();
        $this->setImageReadyForProcessing($image);
        
        // Act
        $job = new ImageProcessJob(['image_id' => $image->id]);
        $job->handle(
            app(\App\Contracts\ImageRepositoryInterface::class),
            app(\App\Contracts\ImagePathServiceInterface::class)
        );
        
        // Assert
        $image->refresh();
        $this->assertNotNull($image->width);
        $this->assertNotNull($image->height);
        $this->assertEquals(1, $image->width); // Test image is 1x1
        $this->assertEquals(1, $image->height);
    }

    /** @test */
    public function it_converts_jpg_to_webp()
    {
        // Arrange
        $image = $this->createTestImage(['filename' => 'test.jpg']);
        $this->setImageReadyForProcessing($image);
        
        // Act
        $job = new ImageProcessJob(['image_id' => $image->id]);
        $job->handle(
            app(\App\Contracts\ImageRepositoryInterface::class),
            app(\App\Contracts\ImagePathServiceInterface::class)
        );
        
        // Assert
        $image->refresh();
        $this->assertEquals('test.webp', $image->filename);
        $this->assertFileExists($image); // WebP exists
        $this->assertFileNotExists($image, 'test.jpg'); // JPG deleted
    }

    /** @test */
    public function it_deletes_jpg_after_webp_conversion()
    {
        // Arrange
        $image = $this->createTestImage(['filename' => 'original.jpg']);
        $this->setImageReadyForProcessing($image);
        
        $jpgPath = "{$image->path}/original.jpg";
        $this->assertTrue(Storage::disk($image->disk)->exists($jpgPath));
        
        // Act
        $job = new ImageProcessJob(['image_id' => $image->id]);
        $job->handle(
            app(\App\Contracts\ImageRepositoryInterface::class),
            app(\App\Contracts\ImagePathServiceInterface::class)
        );
        
        // Assert
        $this->assertFalse(
            Storage::disk($image->disk)->exists($jpgPath),
            'JPG file should be deleted after WebP conversion'
        );
    }

    /** @test */
    public function it_finds_duplicate_by_md5()
    {
        // Arrange
        $original = $this->createTestImage(['filename' => 'original.jpg']);
        $this->setImageReadyForProcessing($original);
        
        // Обрабатываем оригинал
        $job1 = new ImageProcessJob(['image_id' => $original->id]);
        $job1->handle(
            app(\App\Contracts\ImageRepositoryInterface::class),
            app(\App\Contracts\ImagePathServiceInterface::class)
        );
        
        // Создаём дубликат с таким же содержимым
        $duplicate = $this->createTestImage(['filename' => 'duplicate.jpg']);
        $this->setImageReadyForProcessing($duplicate);
        
        // Act
        $job2 = new ImageProcessJob(['image_id' => $duplicate->id]);
        $job2->handle(
            app(\App\Contracts\ImageRepositoryInterface::class),
            app(\App\Contracts\ImagePathServiceInterface::class)
        );
        
        // Assert
        $duplicate->refresh();
        $this->assertEquals($original->id, $duplicate->parent_id);
    }

    /** @test */
    public function it_skips_webp_conversion_for_non_jpg_images()
    {
        // Arrange
        $image = $this->createTestImage(['filename' => 'test.png']);
        $this->setImageReadyForProcessing($image);
        
        // Act
        $job = new ImageProcessJob(['image_id' => $image->id]);
        $job->handle(
            app(\App\Contracts\ImageRepositoryInterface::class),
            app(\App\Contracts\ImagePathServiceInterface::class)
        );
        
        // Assert
        $image->refresh();
        $this->assertEquals('test.png', $image->filename); // Filename unchanged
        $this->assertNotNull($image->hash); // But hash computed
        $this->assertNotNull($image->phash);
    }
}
