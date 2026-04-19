<?php

use App\Enums\FaceStatusEnum;
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
            $table->foreignId('image_id')->nullable()->constrained('images')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedTinyInteger('face_index');
            $table->json('encoding')->nullable();
            $table->float('quality_score')->nullable();
            $table->json('quality_details')->nullable();
            $table->boolean('is_reference')->default(false);
            $table->enum('status', FaceStatusEnum::values())->default(FaceStatusEnum::Process);
            $table->softDeletes();
            $table->timestamps();
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
