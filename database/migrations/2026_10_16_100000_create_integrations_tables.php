<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6: real providers. Encrypted per-restaurant integrations, a log of
 * every provider call (for the health page), payment reconciliation, message
 * deliveries, web push subscriptions, outgoing webhooks and printing.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Per-restaurant provider settings (credentials encrypted at rest).
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // payments | printing | pos
            $table->string('provider', 30); // sadad | printnode | generic_webhook
            $table->string('mode', 10)->default('test'); // test | live
            $table->text('credentials')->nullable(); // encrypted JSON
            $table->json('options')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'kind']);
        });

        // Every call to an outside provider: health page, alerts, support.
        Schema::create('provider_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('operation', 40);
            $table->boolean('ok');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('error', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['provider', 'created_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_transaction', 100)->nullable()->after('reference');
            $table->string('mode', 10)->default('test')->after('gateway');
            $table->integer('fee_minor')->nullable()->after('amount_minor');
            $table->unsignedInteger('refunded_minor')->default(0)->after('fee_minor');
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
            $table->timestamp('last_checked_at')->nullable()->after('refunded_at');
            $table->index('provider_transaction');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_minor');
            $table->string('status', 20); // pending | succeeded | failed
            $table->string('reason', 200)->nullable();
            $table->string('provider_reference', 100)->nullable();
            $table->string('failure_reason', 300)->nullable();
            $table->string('requested_by', 40)->nullable(); // staff:12 | device:3 | customer:5 | system
            $table->timestamps();
        });

        // Messages sent to guests and staff, with delivery status from provider webhooks.
        Schema::create('message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20); // whatsapp | sms | log
            $table->string('purpose', 40); // otp | order_received | order_ready | receipt | alert
            $table->string('to_hash', 64)->index();
            $table->string('to_masked', 20);
            $table->string('provider_message_id', 120)->nullable()->index();
            $table->string('status', 20); // queued | sent | delivered | read | failed
            $table->string('error', 300)->nullable();
            $table->morphs('subject');
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->json('notification_prefs')->nullable()->after('locale');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_prefs')->nullable();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subscriber'); // customer or user
            $table->string('endpoint', 500)->unique();
            $table->string('public_key', 200);
            $table->string('auth_token', 100);
            $table->string('locale', 5)->default('ar');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('quiet_hours_start', 5)->nullable(); // "23:00", restaurant local time
            $table->string('quiet_hours_end', 5)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delay_alerted_at')->nullable()->after('approach_alerted_at');
            $table->timestamp('wait_alerted_at')->nullable()->after('delay_alerted_at');
        });

        // Public API clients (POS and other systems); tokens are Sanctum personal access tokens.
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('scopes');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // Outgoing webhooks for POS and other systems.
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->text('secret'); // encrypted
            $table->json('events');
            $table->string('description', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id');
            $table->string('event', 40);
            $table->json('payload');
            $table->string('status', 20); // pending | delivered | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('response_excerpt', 300)->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['webhook_endpoint_id', 'created_at']);
        });

        // Printers per branch (PrintNode printer ids) and auto-print.
        Schema::table('branches', function (Blueprint $table) {
            $table->json('printers')->nullable(); // {"kitchen": 123, "receipt": 456}
            $table->boolean('auto_print')->default(false);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropIndex(['order_id']));
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn(['printers', 'auto_print']));
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('api_clients');
        Schema::dropIfExists('webhook_endpoints');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['delay_alerted_at', 'wait_alerted_at']));
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn(['quiet_hours_start', 'quiet_hours_end']));
        Schema::dropIfExists('push_subscriptions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_prefs'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('notification_prefs'));
        Schema::dropIfExists('message_deliveries');
        Schema::dropIfExists('refunds');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['provider_transaction']);
            $table->dropColumn(['provider_transaction', 'mode', 'fee_minor', 'refunded_minor', 'refunded_at', 'last_checked_at']);
        });
        Schema::dropIfExists('provider_calls');
        Schema::dropIfExists('integrations');
    }
};
