<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One birthday gift per guest per year (and never two within 365 days, checked in code).
        Schema::create('birthday_gifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->foreignId('wallet_reward_id')->nullable()->constrained('customer_rewards')->nullOnDelete();
            $table->dateTime('sent_at');

            $table->unique(['customer_id', 'year']);
            $table->index(['restaurant_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_gifts');
    }
};
