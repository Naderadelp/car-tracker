<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fill_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable()->constrained('cars')->nullOnDelete();
            $table->decimal('liters', 8, 2);
            $table->integer('odometer');
            $table->decimal('cost_egp', 10, 2);
            $table->date('fill_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fill_ups');
    }
};
