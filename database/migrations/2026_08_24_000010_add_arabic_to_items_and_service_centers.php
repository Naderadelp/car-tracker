<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap F6 — the app ships a full RTL Arabic build. Service items, service
     * centre names and addresses all have an `Ar` variant in the client model
     * and switch with the locale; the service held one Latin value each.
     *
     * Columns rather than resolving server-side from Accept-Language: the gap
     * report is explicit that the client caches both and switches without a
     * refetch, which a request-header approach cannot support.
     *
     * `items.name` is unique; `name_ar` deliberately is NOT. Two catalogue
     * entries may legitimately share an Arabic name, and a unique index there
     * would block admins from recording the obvious translation.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
        });

        Schema::table('service_centers', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('address_ar', 500)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('name_ar');
        });

        Schema::table('service_centers', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'address_ar']);
        });
    }
};
