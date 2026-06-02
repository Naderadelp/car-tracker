<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable()->constrained('cars')->nullOnDelete();
            $table->date('remind_on')->nullable();
            $table->unsignedInteger('remind_at_km')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index('car_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
