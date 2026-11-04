<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Badges a guest has earned (once each, never taken back). Progress is worked out from orders.
        Schema::create('customer_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('badge', 24);
            $table->dateTime('earned_at');
            $table->foreignId('wallet_reward_id')->nullable()->constrained('customer_rewards')->nullOnDelete();
            $table->dateTime('seen_at')->nullable(); // the guest saw the celebration

            $table->unique(['customer_id', 'badge']);
            $table->index(['restaurant_id', 'badge']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_badges');
    }
};
