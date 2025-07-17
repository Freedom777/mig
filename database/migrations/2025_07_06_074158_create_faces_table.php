<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('faces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable(); // nullable for updating afterward
            $table->unsignedBigInteger('image_id')->nullable(); // nullable for updating afterward
            $table->unsignedTinyInteger('face_index');
            $table->string('name')->nullable();
            $table->json('encoding');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('image_id')
                ->references('id')->on('images')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faces');
    }
};
