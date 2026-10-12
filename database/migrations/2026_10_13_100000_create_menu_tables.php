<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: the menu engine. Categories, products (items and combos),
 * reusable modifier groups, menu schedules and per-branch availability,
 * price overrides and daily stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->json('weekdays'); // [0..6], Sunday = 0
            $table->time('starts_at');
            $table->time('ends_at'); // ≤ starts_at = runs past midnight
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('menu_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10)->default('item'); // item | combo
            $table->string('sku', 40)->nullable();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('ingredients')->nullable();
            $table->unsignedInteger('price_minor');
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedSmallInteger('calories')->nullable();
            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->string('image_path')->nullable();
            $table->json('image_variants')->nullable();
            $table->json('allergens')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_limited')->default(false);
            $table->boolean('is_available')->default(true);
            $table->foreignId('meal_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['restaurant_id', 'sku']);
            $table->index(['restaurant_id', 'menu_category_id', 'sort']);
        });

        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('kind', 10)->default('choice'); // choice | remove
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->nullable(); // null = no upper limit
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nested_group_id')->nullable()->constrained('modifier_groups')->nullOnDelete();
            $table->json('name');
            $table->integer('price_delta_minor')->default(0);
            $table->smallInteger('calories_delta')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_available')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('modifier_group_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);

            $table->primary(['product_id', 'modifier_group_id']);
        });

        Schema::create('product_recommendations', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recommended_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);

            $table->primary(['product_id', 'recommended_product_id']);
        });

        Schema::create('combo_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('combo_product_id')->constrained('products')->cascadeOnDelete();
            $table->json('name');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('combo_slot_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('combo_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('price_delta_minor')->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('branch_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('price_override_minor')->nullable();
            $table->timestamp('out_of_stock_until')->nullable();
            $table->unsignedInteger('daily_stock')->nullable();
            $table->unsignedInteger('stock_remaining')->nullable();
            $table->date('stock_date')->nullable();
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->timestamp('low_stock_alerted_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->json('menu_layout')->nullable()->after('loyalty_points_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('menu_layout');
        });
        Schema::dropIfExists('branch_products');
        Schema::dropIfExists('combo_slot_options');
        Schema::dropIfExists('combo_slots');
        Schema::dropIfExists('product_recommendations');
        Schema::dropIfExists('modifier_group_product');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('products');
        Schema::dropIfExists('menu_categories');
        Schema::dropIfExists('menu_schedules');
    }
};
