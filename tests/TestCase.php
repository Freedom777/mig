<?php

namespace Tests;

use App\Models\Image;
use App\Providers\ImageServiceProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureSqliteConnection();
        $this->configureQueueConnection();
        $this->createDatabaseSchema();
        $this->registerProviders();
        $this->setDefaultConfig();
    }

    protected function tearDown(): void
    {
        $this->dropAllTables();
        parent::tearDown();
    }

    protected function configureSqliteConnection(): void
    {
        config([
            'database.connections.sqlite_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'database.default' => 'sqlite_testing',
        ]);
    }

    /**
     * Queue остаётся sync, но в тестах QueueAbleTrait 
     * используется Bus::fake() для перехвата dispatch.
     */
    protected function configureQueueConnection(): void
    {
        config([
            'queue.default' => 'sync',
        ]);
    }

    protected function registerProviders(): void
    {
        $this->app->register(ImageServiceProvider::class);
    }

    protected function setDefaultConfig(): void
    {
        config([
            'image.paths.disk' => 'private',
            'image.paths.images' => 'images',
            'image.paths.debug_subdir' => 'debug',
            'image.thumbnails.width' => 300,
            'image.thumbnails.height' => 200,
            'image.thumbnails.method' => 'cover',
            'image.thumbnails.dir_format' => '{width}x{height}',
            'image.thumbnails.postfix' => '_{method}_{width}x{height}',
            'image.processing.mode' => 'queue',
            'image.processing.dry_run' => false,
            'image.processing.debug' => false,
            'image.processing.phash_distance_threshold' => 5,
            'queue.name.images' => 'images',
            'queue.name.thumbnails' => 'thumbnails',
            'queue.name.metadatas' => 'metadatas',
            'queue.name.geolocations' => 'geolocations',
            'queue.name.faces' => 'faces',
        ]);
    }

    protected function createDatabaseSchema(): void
    {
        Schema::create('images', function ($table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('image_geolocation_point_id')->nullable();
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('filename');
            $table->string('debug_filename')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('size')->nullable();
            $table->binary('hash')->nullable();
            $table->binary('phash')->nullable();
            $table->dateTime('created_at_file')->nullable();
            $table->dateTime('updated_at_file')->nullable();
            $table->text('metadata')->nullable();
            $table->boolean('faces_checked')->default(false);
            $table->string('thumbnail_path')->nullable();
            $table->string('thumbnail_filename')->nullable();
            $table->string('thumbnail_method')->nullable();
            $table->string('thumbnail_width')->nullable();
            $table->string('thumbnail_height')->nullable();
            $table->timestamps();
            $table->string('status')->default('process');
            $table->string('last_error')->nullable();

            $table->index(['disk', 'path', 'filename'], 'disk_path_filename_index');
        });

        Schema::create('queues', function ($table) {
            $table->id();
            $table->binary('queue_key');
            $table->timestamp('created_at')->useCurrent();
            $table->unique('queue_key', 'queues_queue_key_unique');
        });

        Schema::create('faces', function ($table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('image_id')->nullable();
            $table->unsignedTinyInteger('face_index');
            $table->string('name')->nullable();
            $table->text('encoding')->nullable();
            $table->string('status')->default('process');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('image_geolocation_addresses', function ($table) {
            $table->id();
            $table->bigInteger('osm_id');
            $table->text('osm_area')->nullable();
        });

        Schema::create('image_geolocation_points', function ($table) {
            $table->id();
            $table->unsignedBigInteger('image_geolocation_address_id')->nullable();
            $table->text('coordinates');
        });
    }

    protected function dropAllTables(): void
    {
        Schema::dropIfExists('faces');
        Schema::dropIfExists('image_geolocation_points');
        Schema::dropIfExists('image_geolocation_addresses');
        Schema::dropIfExists('queues');
        Schema::dropIfExists('images');
    }

    protected function createTestImage(array $attributes = []): Image
    {
        $defaults = [
            'disk' => 'private',
            'path' => 'images/test',
            'filename' => 'test_' . uniqid() . '.jpg',
            'size' => 1024000,
            'status' => 'process',
            'created_at_file' => now(),
            'updated_at_file' => now(),
        ];

        return Image::create(array_merge($defaults, $attributes));
    }

    protected function createTestImages(int $count, array $attributes = []): array
    {
        $images = [];
        for ($i = 0; $i < $count; $i++) {
            $images[] = $this->createTestImage($attributes);
        }
        return $images;
    }

    protected function isUsingMysql(): bool
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        return $driver === 'mysql';
    }

    protected function skipIfNotMysql(string $reason = 'Requires MySQL'): void
    {
        if (!$this->isUsingMysql()) {
            $this->markTestSkipped($reason);
        }
    }
}
