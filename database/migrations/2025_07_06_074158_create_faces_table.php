<?php

use App\Models\Face;
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
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('face_index');
            // $table->string('name')->nullable();
            $table->json('encoding')->nullable();
            $table->float('quality_score')->nullable();
            $table->json('quality_details')->nullable();
            $table->boolean('is_reference')->default(false);
            $table->enum('status', [Face::STATUS_PROCESS, Face::STATUS_UNKNOWN, Face::STATUS_NOT_FACE, Face::STATUS_OK])->default(Face::STATUS_PROCESS);
            $table->softDeletes();
            $table->timestamps();

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
