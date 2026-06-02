<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fill_ups', function (Blueprint $table) {
            $table->enum('fuel_type', ['92', '95', 'electric'])->nullable();
            $table->decimal('liters', 8, 2)->nullable()->change();
            $table->decimal('station_lat', 10, 8)->nullable();
            $table->decimal('station_lng', 11, 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fill_ups', function (Blueprint $table) {
            $table->dropColumn(['fuel_type', 'station_lat', 'station_lng']);
        });

        Schema::table('fill_ups', function (Blueprint $table) {
            $table->decimal('liters', 8, 2)->nullable(false)->change();
        });
    }
};
