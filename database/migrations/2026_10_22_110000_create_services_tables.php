<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What each service costs, set by the platform (per branch, per month).
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->string('service', 20)->unique(); // drive_thru | ready_call | rewards
            $table->unsignedInteger('price_minor')->default(0);
            $table->char('currency', 3)->default('QAR');
            $table->unsignedSmallInteger('demo_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // The services a restaurant has, with their demo and price.
        Schema::create('restaurant_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('service', 20);
            $table->string('status', 12); // demo | active | cancelling | ended
            $table->unsignedInteger('unit_price_minor')->default(0);
            $table->timestamp('demo_ends_at')->nullable();
            $table->boolean('keep_after_demo')->default(false);
            $table->timestamp('demo_used_at')->nullable();
            $table->timestamp('demo_reminded_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['restaurant_id', 'service']);
            $table->index(['status', 'demo_ends_at']);
        });

        $now = now();
        DB::table('service_prices')->insert([
            ['service' => 'drive_thru', 'price_minor' => 34900, 'currency' => 'QAR', 'demo_days' => 14, 'is_active' => true, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['service' => 'ready_call', 'price_minor' => 14900, 'currency' => 'QAR', 'demo_days' => 14, 'is_active' => true, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['service' => 'rewards', 'price_minor' => 19900, 'currency' => 'QAR', 'demo_days' => 14, 'is_active' => true, 'sort' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Existing clients keep what they have today: Drive-Thru, plus Rewards
        // where their plan (or an override) already includes loyalty. The
        // plan's per-branch price moves onto Drive-Thru so invoices don't change.
        $restaurants = DB::table('restaurants')->get(['id', 'feature_overrides']);

        foreach ($restaurants as $restaurant) {
            $subscription = DB::table('subscriptions')->where('restaurant_id', $restaurant->id)->orderByDesc('id')->first();
            $plan = $subscription ? DB::table('plans')->where('id', $subscription->plan_id)->first() : null;
            $features = $plan ? (array) json_decode((string) $plan->features, true) : [];
            $overrides = (array) json_decode((string) ($restaurant->feature_overrides ?? '{}'), true);
            $loyalty = ($overrides['loyalty'] ?? in_array('loyalty', $features, true)) === true;
            $status = $subscription && $subscription->status === 'trialing' ? 'demo' : 'active';

            DB::table('restaurant_services')->insert([
                'restaurant_id' => $restaurant->id,
                'service' => 'drive_thru',
                'status' => $status,
                'unit_price_minor' => $subscription->unit_price_minor ?? 0,
                'demo_ends_at' => $status === 'demo' ? $subscription->trial_ends_at : null,
                'keep_after_demo' => $status === 'demo',
                'demo_used_at' => $status === 'demo' ? $now : null,
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($loyalty) {
                DB::table('restaurant_services')->insert([
                    'restaurant_id' => $restaurant->id,
                    'service' => 'rewards',
                    'status' => $status,
                    'unit_price_minor' => 0, // included in what they already pay
                    'demo_ends_at' => $status === 'demo' ? $subscription->trial_ends_at : null,
                    'keep_after_demo' => $status === 'demo',
                    'demo_used_at' => $status === 'demo' ? $now : null,
                    'started_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Plans become tiers (limits and add-ons) with no fee of their own by
        // default; the services carry the price. A custom-priced tier keeps its fee.
        DB::table('subscriptions')->update(['unit_price_minor' => 0]);
        DB::table('plans')->whereNotNull('price_minor')->update(['price_minor' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_services');
        Schema::dropIfExists('service_prices');
    }
};
