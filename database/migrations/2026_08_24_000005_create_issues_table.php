<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap B5 — the photo-first fault log. The app has a whole screen for it and
     * the service had no equivalent; the nearest neighbour was `reminders`,
     * which is a future date rather than a recorded event.
     *
     * `resolved_at` doubles as the resolved flag: null means unresolved. A
     * separate boolean would let the two disagree.
     */
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('occurred_at');
            $table->string('title');
            $table->string('severity', 16);
            $table->text('summary')->nullable();
            $table->text('solution')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // The attention list reads exactly this: unresolved, by severity.
            $table->index(['car_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
