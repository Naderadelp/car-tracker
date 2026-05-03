<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('car_model_id')->nullable()->constrained('car_models')->nullOnDelete();
            $table->integer('year');
            $table->integer('current_km')->default(0);
            $table->boolean('has_warranty')->default(false);
            $table->integer('warranty_limit_km')->nullable();
            $table->date('warranty_expiry_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
