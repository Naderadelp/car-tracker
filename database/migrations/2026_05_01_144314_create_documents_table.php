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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->nullOnDelete()->constrained('users');
            $table->foreignId('car_id')->nullable()->nullOnDelete()->constrained('cars');
            $table->enum('type', [
                'vehicle_license',
                'insurance_policy',
                'registration',
                'inspection_certificate',
                'driver_license',
                'finance_contract',
            ]);
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
