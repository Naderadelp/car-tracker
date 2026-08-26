<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap F4 — every history row in the app reads title · workshop · category
     * ("Brake pads, El Nasr Auto, service"), but car_logs stored only a
     * service_id, an odometer, a cost and a date. Ad-hoc work — service_id
     * null — therefore recorded a cost with no description at all.
     *
     * All nullable: existing rows have none of this and must keep working.
     */
    public function up(): void
    {
        Schema::table('car_logs', function (Blueprint $table) {
            $table->string('title')->nullable()->after('service_id');
            $table->string('workshop')->nullable()->after('title');
            $table->string('category', 64)->nullable()->after('workshop');
            $table->text('notes')->nullable()->after('performed_at');
        });
    }

    public function down(): void
    {
        Schema::table('car_logs', function (Blueprint $table) {
            $table->dropColumn(['title', 'workshop', 'category', 'notes']);
        });
    }
};
