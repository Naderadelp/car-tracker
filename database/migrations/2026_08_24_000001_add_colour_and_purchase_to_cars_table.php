<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR-003 (gap F8): the app collects a paint colour at sign-up and the
     * service had nowhere to put it.
     *
     * FR-034 (gap A3): purchase price and date are the only inputs the
     * valuation screen needs that cannot be derived. Decision D1 keeps the
     * estimate local — no market-data provider.
     */
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('color', 32)->nullable()->after('car_model_id');
            $table->decimal('purchase_price_egp', 12, 2)->nullable()->after('warranty_expiry_date');
            $table->date('purchased_at')->nullable()->after('purchase_price_egp');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['color', 'purchase_price_egp', 'purchased_at']);
        });
    }
};
