<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-011, FR-012. Both app stores require in-app account deletion from any
     * app that offers account creation, so this blocks store review rather than
     * merely integration.
     *
     * A soft delete rather than a hard one: `cars`, `documents`, `fill_ups`,
     * `trips` and the rest hold foreign keys to this row, and several of those
     * constraints are `nullOnDelete`, which would silently orphan a driver's
     * records instead of removing them. Soft-deleting makes the account
     * inaccessible immediately — which is the outcome the driver and the store
     * requirement both care about — while the controller removes the dependent
     * records and stored files explicitly.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
