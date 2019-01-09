<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->increments('id');
            $table->string('path', 255);
            $table->string('filename', 255);
            $table->string('thumb', 255);
            $table->string('new_filename', 255);
            $table->string('sha_checksum', 40);
            $table->integer('size');
            $table->integer('width');
            $table->integer('height');
            $table->integer('exif_orientation');
            $table->dateTimeTz('exif_created_at');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('files');
    }
}
