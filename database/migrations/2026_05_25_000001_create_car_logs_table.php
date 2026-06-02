<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable()->constrained('cars')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->unsignedInteger('odometer_at_service');
            $table->decimal('actual_cost', 10, 2);
            $table->date('performed_at');
            $table->timestamps();

            $table->index('car_id');
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_logs');
    }
};
