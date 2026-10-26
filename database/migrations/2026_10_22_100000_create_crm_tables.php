<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guest stats (picked-up orders only) and marketing consent with its proof.
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->unsignedInteger('picked_up_count')->default(0);
            $table->unsignedInteger('orders_90d')->default(0);
            $table->unsignedBigInteger('spent_90d_minor')->default(0);
            $table->unsignedInteger('avg_order_minor')->default(0);
            $table->timestamp('offers_consent_at')->nullable();
            $table->string('offers_consent_source', 20)->nullable(); // checkout | account | qr | staff
            $table->string('offers_consent_version', 20)->nullable();
            $table->timestamp('offers_withdrawn_at')->nullable();
            $table->index(['restaurant_id', 'last_order_at']);
        });

        // Nightly segments, replaced in one go per restaurant.
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('segment', 20);
            $table->unsignedBigInteger('product_id')->default(0); // "frequent buyer of" only
            $table->timestamp('computed_at');
            $table->unique(['customer_id', 'segment', 'product_id']);
            $table->index(['restaurant_id', 'segment', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segments');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'last_order_at']);
            $table->dropColumn(['first_order_at', 'last_order_at', 'picked_up_count', 'orders_90d', 'spent_90d_minor', 'avg_order_minor', 'offers_consent_at', 'offers_consent_source', 'offers_consent_version', 'offers_withdrawn_at']);
        });
    }
};
