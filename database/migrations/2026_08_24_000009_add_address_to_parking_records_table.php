<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap F7 — a saved location holds a label, a reverse-geocoded address and a
     * personal note: three distinct strings. The service had `name` and
     * `description` only, so the address had to be crammed into one of them.
     *
     * 500 chars matches service_centers.address.
     */
    public function up(): void
    {
        Schema::table('parking_records', function (Blueprint $table) {
            $table->string('address', 500)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('parking_records', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
