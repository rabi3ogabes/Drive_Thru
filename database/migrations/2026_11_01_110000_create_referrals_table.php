<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each guest's invite code (/{restaurant}/invite/{code}), made the first time it is shown.
        Schema::table('customers', function (Blueprint $table) {
            $table->string('referral_code', 8)->nullable()->after('phone');
            $table->unique(['restaurant_id', 'referral_code']);
        });

        // The browser a guest signed in from (hash of a random first-party cookie), for fraud checks.
        Schema::table('customer_sessions', function (Blueprint $table) {
            $table->char('device_hash', 64)->nullable()->after('user_agent');
            $table->index(['customer_id', 'device_hash']);
        });

        // A friend who joined through an invite: pending until their first picked-up order.
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referrer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('friend_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->string('status', 12)->default('pending'); // pending | rewarded | rejected
            $table->string('reason', 24)->nullable(); // why it was rejected
            $table->char('device_hash', 64)->nullable(); // the friend's browser when they joined
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('referrer_reward_id')->nullable()->constrained('customer_rewards')->nullOnDelete();
            $table->foreignId('friend_reward_id')->nullable()->constrained('customer_rewards')->nullOnDelete();
            $table->dateTime('joined_at');
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index(['referrer_id', 'status', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');

        Schema::table('customer_sessions', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'device_hash']);
            $table->dropColumn('device_hash');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['restaurant_id', 'referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
