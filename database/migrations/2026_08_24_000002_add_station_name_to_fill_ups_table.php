<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-009 (gap B3). The fuel form collects a station *name* and the service
     * only ever stored station coordinates, so the name — shown on every
     * ledger row and in the fill-up detail sheet — had nowhere to go.
     *
     * `fuel_type`, `station_lat` and `station_lng` already exist; only the name
     * is new. `odometer` stays NOT NULL and its fallback to cars.current_km is
     * controller logic (FR-010).
     */
    public function up(): void
    {
        Schema::table('fill_ups', function (Blueprint $table) {
            $table->string('station_name')->nullable()->after('fuel_type');
        });
    }

    public function down(): void
    {
        Schema::table('fill_ups', function (Blueprint $table) {
            $table->dropColumn('station_name');
        });
    }
};
