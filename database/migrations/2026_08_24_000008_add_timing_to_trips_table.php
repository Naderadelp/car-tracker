<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap F5 — Trips History lists start and end time, duration and max speed
     * per trip, and the Home chart buckets distance by weekday from those
     * timestamps. All of it was discarded the moment the trip was posted.
     *
     * `duration_seconds` is stored rather than derived from the two timestamps
     * because the client measures it directly, and a trip may arrive with a
     * duration but no wall-clock times.
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('total_distance_km');
            $table->timestamp('ended_at')->nullable()->after('started_at');
            $table->unsignedInteger('duration_seconds')->nullable()->after('ended_at');
            $table->decimal('max_speed_kmh', 6, 2)->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'ended_at', 'duration_seconds', 'max_speed_kmh']);
        });
    }
};
