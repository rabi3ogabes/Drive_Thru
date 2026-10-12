<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('type', 20);
            $table->unsignedInteger('value_bp')->nullable();       // percent off
            $table->unsignedInteger('value_minor')->nullable();    // fixed off, or bonus points
            $table->unsignedSmallInteger('buy_qty')->nullable();
            $table->unsignedSmallInteger('get_qty')->nullable();
            $table->foreignId('free_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('combo_product_ids')->nullable();
            $table->unsignedInteger('combo_price_minor')->nullable();
            $table->string('scope', 20)->default('all');            // all | categories | products
            $table->json('scope_ids')->nullable();
            $table->json('branch_ids')->nullable();                 // null = every branch
            $table->boolean('first_order')->default(false);
            $table->boolean('birthday')->default(false);
            $table->json('days')->nullable();                       // 0 = Sunday … 6 = Saturday
            $table->string('time_from', 5)->nullable();             // restaurant local time
            $table->string('time_to', 5)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('min_subtotal_minor')->default(0);
            $table->boolean('requires_coupon')->default(false);
            $table->boolean('stackable')->default(false);
            $table->smallInteger('priority')->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'archived_at']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'code']);
        });

        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('discount_minor')->default(0);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['promotion_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('promotions');
    }
};
