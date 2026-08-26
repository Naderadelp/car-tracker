<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap B4 — the Costs tab is a whole bottom-nav screen with nothing behind
     * it. Insurance, tyres, fines, washing and parking fees had nowhere to live.
     *
     * Decision D2 makes this a *unified* ledger rather than a manual one: fuel
     * records and maintenance entries carry across automatically, the driver
     * may overwrite a carried-across amount, and a manual duplicate can be
     * deleted afterwards.
     *
     * `source_type` / `source_id` are null for an entry the driver typed in.
     * `amount_overridden` marks a carried-across row the driver has corrected,
     * after which the observers stop touching it (FR-046).
     */
    public function up(): void
    {
        Schema::create('costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
            // Denormalised so CostRepositoryEloquent::scopeToUser() can filter
            // without a join on every read (constitution Principle I).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('spent_at');
            $table->string('title');
            $table->decimal('amount_egp', 10, 2);
            $table->string('category', 32);
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('amount_overridden')->default(false);
            $table->timestamps();

            $table->index(['car_id', 'spent_at']);

            /*
             * The backstop for the whole carry-across mechanism: a source
             * record can never produce two ledger rows. Enforced by the
             * database rather than by observer discipline, because two
             * observers plus an override flag is three chances to drift.
             */
            $table->unique(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costs');
    }
};
