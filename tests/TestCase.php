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
     * 
     * Примечание: SQLite не поддерживает ENUM, POINT, POLYGON.
     * Используем приближённые типы для тестирования.
     */
    protected function createDatabaseSchema(): void
    {
        // images
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
            $table->binary('hash')->nullable(); // binary(16)
            $table->binary('phash')->nullable(); // binary(8)
            $table->dateTime('created_at_file')->nullable();
            $table->dateTime('updated_at_file')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('faces_checked')->default(false);
            $table->string('thumbnail_path')->nullable();
            $table->string('thumbnail_filename')->nullable();
            $table->string('thumbnail_method')->nullable();
            $table->string('thumbnail_width')->nullable(); // varchar в MySQL
            $table->string('thumbnail_height')->nullable(); // varchar в MySQL
            $table->timestamps();
            $table->string('status')->default('process'); // enum в MySQL
            $table->string('last_error')->nullable();

            $table->index(['disk', 'path', 'filename'], 'disk_path_filename_index');
            $table->index('faces_checked', 'faces_checked_index');
            $table->index('hash', 'hash_index');
            $table->index('phash', 'phash_index');
        });

        // queues (дедупликация)
        Schema::create('queues', function ($table) {
            $table->id();
            $table->binary('queue_key'); // binary(16) = MD5
            $table->timestamp('created_at')->useCurrent();

            $table->unique('queue_key', 'queues_queue_key_unique');
        });

        // faces
        Schema::create('faces', function ($table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('image_id')->nullable();
            $table->unsignedTinyInteger('face_index');
            $table->string('name')->nullable();
            $table->json('encoding')->nullable(); // JSON в MySQL
            $table->string('status')->default('process'); // enum в MySQL
            $table->timestamps();
            $table->softDeletes();

            $table->index('image_id', 'faces_image_id_foreign');
        });

        // image_geolocation_addresses
        Schema::create('image_geolocation_addresses', function ($table) {
            $table->id();
            $table->bigInteger('osm_id');
            // osm_area - POLYGON в MySQL, в SQLite просто текст
            $table->text('osm_area')->nullable();
        });

        // image_geolocation_points
        Schema::create('image_geolocation_points', function ($table) {
            $table->id();
            $table->unsignedBigInteger('image_geolocation_address_id')->nullable();
            // coordinates - POINT в MySQL, в SQLite храним как текст
            $table->text('coordinates');
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
