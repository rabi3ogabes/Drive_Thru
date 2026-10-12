<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: guests (customers per restaurant), phone codes, vehicles,
 * favourites, orders with items and status history, and payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('name', 80)->nullable();
            $table->date('birthday')->nullable();
            $table->string('locale', 5)->nullable();
            $table->foreignId('last_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedBigInteger('total_spent_minor')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'phone']);
        });

        Schema::create('customer_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->string('code_hash', 64);
            $table->string('channel', 12);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'phone', 'created_at']);
        });

        Schema::create('customer_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('nickname', 40)->nullable();
            $table->string('plate', 20);
            $table->string('make', 40)->nullable();
            $table->string('model', 40)->nullable();
            $table->string('color', 30)->nullable();
            $table->string('type', 20)->default('car');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'product_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lane_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('short_code', 8)->unique();
            $table->string('pickup_code', 6);
            $table->string('status', 20)->index();
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('payment_method', 20);
            $table->char('currency', 3);
            $table->unsignedInteger('subtotal_minor');
            $table->unsignedInteger('discount_minor')->default(0);
            $table->unsignedInteger('tax_minor')->default(0);
            $table->unsignedInteger('service_fee_minor')->default(0);
            $table->unsignedInteger('total_minor');
            $table->string('timing', 12)->default('now');
            $table->unsignedSmallInteger('delay_minutes')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('prepare_at')->nullable()->index();
            $table->timestamp('ready_estimate_at')->nullable();
            $table->unsignedSmallInteger('prep_minutes');
            $table->json('vehicle')->nullable();
            $table->string('notes', 300)->nullable();
            $table->json('source')->nullable();
            $table->string('locale', 5)->default('ar');
            $table->string('idempotency_key', 64);
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'number']);
            $table->unique(['restaurant_id', 'idempotency_key']);
            $table->index(['branch_id', 'status']);
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->json('name');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('unit_price_minor');
            $table->integer('options_minor')->default(0);
            $table->unsignedInteger('line_total_minor');
            $table->json('options')->nullable();
            $table->string('notes', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from', 20)->nullable();
            $table->string('to', 20);
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);
            $table->string('gateway', 20);
            $table->string('status', 20);
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reference', 100)->nullable();
            $table->string('redirect_url', 500)->nullable();
            $table->string('failure_reason', 200)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customer_favorites');
        Schema::dropIfExists('customer_vehicles');
        Schema::dropIfExists('otp_requests');
        Schema::dropIfExists('customer_sessions');
        Schema::dropIfExists('customers');
    }
};
