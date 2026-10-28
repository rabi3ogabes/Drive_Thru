<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An offer sent to guests who agreed to receive offers, with its results.
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('segments')->nullable(); // null = everyone who agreed
            $table->string('channel', 10); // whatsapp | sms | push
            $table->json('message'); // {en, ar}
            $table->foreignId('reward_id')->nullable()->constrained('rewards')->nullOnDelete();
            $table->string('link_code', 12)->unique(); // ?campaign= on the order link
            $table->string('status', 12)->default('draft'); // draft | scheduled | sending | sent | cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable(); // held back by quiet hours or the daily limit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedBigInteger('revenue_minor')->default(0);
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
            $table->index(['restaurant_id', 'status']);
        });

        // One guest of one campaign: why it was or wasn't sent, and the order it brought.
        Schema::create('campaign_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10)->default('pending'); // pending | sending | sent | failed | skipped
            $table->string('skip_reason', 20)->nullable();
            $table->string('channel', 10)->nullable();
            $table->string('locale', 2)->default('ar');
            $table->foreignId('message_delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('revenue_minor')->default(0);
            $table->timestamp('attributed_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'customer_id']);
            $table->index(['customer_id', 'sent_at']);
            $table->index(['campaign_id', 'status']);
            $table->index(['restaurant_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_messages');
        Schema::dropIfExists('campaigns');
    }
};
