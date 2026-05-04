<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
            $table->decimal('start_lat', 10, 8);
            $table->decimal('start_lng', 11, 8);
            $table->decimal('end_lat', 10, 8);
            $table->decimal('end_lng', 11, 8);
            $table->decimal('total_distance_km', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
