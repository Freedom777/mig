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
        Schema::create('image_geolocation_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('image_geolocation_address_id')->nullable();
            $table->geography('coordinates', subtype: 'point', srid: 4326);

            $table->foreign('image_geolocation_address_id')
                ->references('id')->on('image_geolocation_addresses')
                ->onDelete('set null')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_geolocation_points');
    }
};
