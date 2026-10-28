<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            // Price per branch per billing interval, in minor units (dirhams). Null = custom pricing.
            $table->unsignedInteger('price_minor')->nullable();
            $table->char('currency', 3)->default('QAR');
            $table->string('interval', 10)->default('month');
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->json('limits');
            $table->json('features');
            $table->boolean('is_public')->default(true);
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_minor')->default(0);
            $table->char('currency', 3)->default('QAR');
            $table->string('interval', 10)->default('month');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable()->index();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status', 10)->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedSmallInteger('tax_rate_bp')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->dateTime('issued_at');
            $table->dateTime('due_at')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->json('description');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('sequences', function (Blueprint $table) {
            $table->string('name', 60)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
