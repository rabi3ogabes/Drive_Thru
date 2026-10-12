<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The discounts on an order, frozen as the guest saw them (labels in both languages).
        Schema::create('order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // promotion | coupon | reward | points
            $table->unsignedBigInteger('source_id')->default(0);
            $table->json('label');
            $table->unsignedInteger('amount_minor')->default(0);
            $table->unsignedInteger('bonus_points')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index('order_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('points_redeemed')->default(0);
            $table->unsignedInteger('points_earned')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['points_redeemed', 'points_earned']));
        Schema::dropIfExists('order_adjustments');
    }
};
