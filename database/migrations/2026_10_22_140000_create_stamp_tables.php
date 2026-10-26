<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Buy 5, the 6th is free": what counts, how many, and the reward from the catalog.
        Schema::create('stamp_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('target_type', 10); // product | category
            $table->unsignedBigInteger('target_id');
            $table->unsignedTinyInteger('stamps_needed');
            $table->foreignId('reward_id')->constrained('rewards')->restrictOnDelete();
            $table->unsignedSmallInteger('card_expiry_days')->nullable();
            $table->unsignedTinyInteger('daily_cap')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['restaurant_id', 'is_active']);
        });

        // One card per guest per program.
        Schema::create('stamp_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stamp_program_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stamps')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->timestamp('last_stamped_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'stamp_program_id']);
        });

        // Every stamp added or taken back, with who and why.
        Schema::create('stamp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stamp_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('delta');
            $table->string('source', 12); // order | counter | reversal | completed | expired
            $table->foreignId('wallet_reward_id')->nullable()->constrained('customer_rewards')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['stamp_card_id', 'created_at']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_events');
        Schema::dropIfExists('stamp_cards');
        Schema::dropIfExists('stamp_programs');
    }
};
