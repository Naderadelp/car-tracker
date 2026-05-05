<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_model_id')->nullable()->constrained('car_models')->nullOnDelete();
            $table->foreignId('car_id')->nullable()->constrained('cars')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('km');
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->index(['car_model_id', 'km']);
            $table->index(['car_id', 'km']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
