<?php

use App\Models\Image;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable(); // nullable for updating afterward
            // ALTER TABLE `images` ADD `parent_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `id`, ADD INDEX (`parent_id`);

            $table->unsignedBigInteger('image_geolocation_point_id')->nullable();

            $table->string('disk')->nullable();

            $table->string('path')->nullable();
            $table->string('filename');
            $table->string('debug_filename')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('size')->nullable(); // Filesize
            $table->binary('hash', 16); // BINARY(16) для MD5
            $table->binary('phash', length: 8, fixed: true); // BINARY(8) для perceptual hash
            $table->dateTime('created_at_file')->nullable();
            $table->dateTime('updated_at_file')->nullable();
            $table->json('metadata')->nullable();

            $table->boolean('faces_checked')->default(false);

            $table->string('thumbnail_path')->nullable();
            $table->string('thumbnail_filename')->nullable();
            $table->string('thumbnail_method')->nullable();
            $table->string('thumbnail_width')->nullable();
            $table->string('thumbnail_height')->nullable();

            $table->timestamps();

            $table->enum('status', [Image::STATUS_PROCESS, Image::STATUS_NOT_PHOTO, Image::STATUS_RECHECK, Image::STATUS_OK])->default(Image::STATUS_PROCESS);
            $table->string('last_error')->nullable();

            $table->index(['disk', 'path', 'filename'], 'disk_path_filename_index');
            $table->index(['faces_checked'], 'faces_checked_index');
            $table->foreign('image_geolocation_point_id')
                ->references('id')->on('image_geolocation_points')
                ->onDelete('set null')->onUpdate('restrict');

            /*// Для GPSPosition
            $table->string('gps_position')
                ->virtualAs("JSON_UNQUOTE(metadata->'$.GPSPosition')")
                ->index();

            // Для GPSLatitude + GPSLongitude (составной индекс)
            $table->decimal('gps_latitude', 10, 8)
                ->virtualAs("CAST(metadata->'$.GPSLatitude' AS DECIMAL(10,8))")
                ->nullable();

            $table->decimal('gps_longitude', 10, 8)
                ->virtualAs("CAST(metadata->'$.GPSLongitude' AS DECIMAL(10,8))")
                ->nullable();

            $table->index(['gps_latitude', 'gps_longitude']);*/
        });

        DB::statement('ALTER TABLE `images` MODIFY `hash` BINARY(16)');
        DB::statement('CREATE INDEX `hash_index` ON `images` (`hash`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
