<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fill_ups', function (Blueprint $table) {
            $table->decimal('tank_percentage', 5, 2)->nullable()->after('liters');
        });
    }

    public function down(): void
    {
        Schema::table('fill_ups', function (Blueprint $table) {
            $table->dropColumn('tank_percentage');
        });
    }
};
