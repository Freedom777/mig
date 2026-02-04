<?php

namespace Tests;

use App\Models\Image;
use App\Providers\ImageServiceProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureSqliteConnection();
        $this->createDatabaseSchema();
        $this->registerProviders();
        $this->setDefaultConfig();
    }

    /**
     * Teardown the test environment.
     */
    protected function tearDown(): void
    {
        $this->dropAllTables();
        parent::tearDown();
    }

    /**
     * Настраивает SQLite подключение для тестов
     */
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
     * Регистрирует провайдеры
     */
    protected function registerProviders(): void
    {
        $this->app->register(ImageServiceProvider::class);
    }

    /**
     * Устанавливает дефолтные конфиги для тестов
     */
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

    /**
     * Создаёт схему БД
     */
    protected function createDatabaseSchema(): void
    {
        // images
        Schema::create('images', function ($table) {
            $table->id();
            $table->string('disk', 50)->default('private');
            $table->string('path', 500);
            $table->string('filename', 255);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->bigInteger('size')->nullable();
            $table->binary('hash')->nullable();
            $table->binary('phash')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('thumbnail_filename')->nullable();
            $table->string('thumbnail_method', 20)->nullable();
            $table->integer('thumbnail_width')->nullable();
            $table->integer('thumbnail_height')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('image_geolocation_point_id')->nullable();
            $table->string('debug_filename')->nullable();
            $table->boolean('faces_checked')->default(false);
            $table->string('status', 20)->default('process');
            $table->text('last_error')->nullable();
            $table->timestamp('created_at_file')->nullable();
            $table->timestamp('updated_at_file')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['disk', 'path', 'filename']);
        });

        // queues (дедупликация)
        Schema::create('queues', function ($table) {
            $table->id();
            $table->binary('queue_key');
            $table->string('queue_name', 100);
            $table->string('job_class', 255);
            $table->json('job_data')->nullable();
            $table->timestamps();

            $table->index('queue_name');
        });

        // faces
        Schema::create('faces', function ($table) {
            $table->id();
            $table->unsignedBigInteger('image_id');
            $table->integer('face_index')->default(0);
            $table->binary('encoding')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });

        // image_geolocation_addresses
        Schema::create('image_geolocation_addresses', function ($table) {
            $table->id();
            $table->bigInteger('osm_id')->nullable();
            $table->json('address')->nullable();
            $table->timestamps();
        });

        // image_geolocation_points
        Schema::create('image_geolocation_points', function ($table) {
            $table->id();
            $table->unsignedBigInteger('image_geolocation_address_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Удаляет все таблицы
     */
    protected function dropAllTables(): void
    {
        Schema::dropIfExists('faces');
        Schema::dropIfExists('image_geolocation_points');
        Schema::dropIfExists('image_geolocation_addresses');
        Schema::dropIfExists('queues');
        Schema::dropIfExists('images');
    }

    /**
     * Создаёт тестовое изображение
     */
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

    /**
     * Создаёт несколько тестовых изображений
     */
    protected function createTestImages(int $count, array $attributes = []): array
    {
        $images = [];
        for ($i = 0; $i < $count; $i++) {
            $images[] = $this->createTestImage($attributes);
        }
        return $images;
    }
}
