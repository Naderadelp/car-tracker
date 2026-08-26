<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap F3 — drivers add their own lines to a service interval, a label and a
     * price each, and those extras had nowhere to go.
     *
     * The pivot held only service_id / item_id / car_id, and `items.name` is
     * globally unique on an admin-managed catalogue. So a driver adding
     * "Cabin filter, 450" either collided with a catalogue row or created one,
     * and could not carry their own price either way.
     *
     * Both columns are nullable overrides. A row with an item_id and no
     * overrides behaves exactly as before; a row with overrides and no item_id
     * is a driver's own line. The resource resolves override-then-catalogue.
     */
    public function up(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->string('name')->nullable()->after('item_id');
            $table->decimal('price', 10, 2)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->dropColumn(['name', 'price']);
        });
    }
};
