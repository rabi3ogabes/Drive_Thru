<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One loyalty program per restaurant. Off, and every value empty, until the owner sets it up.
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('earn_points_per_unit')->nullable(); // per 1 major currency unit
            $table->unsignedInteger('redeem_points')->nullable();        // this many points…
            $table->unsignedInteger('redeem_value_minor')->nullable();   // …are worth this much off
            $table->unsignedTinyInteger('max_redeem_percent')->nullable();
            $table->unsignedInteger('min_redeem_points')->nullable();
            $table->unsignedSmallInteger('expiry_months')->nullable();   // null = points never expire
            $table->unsignedSmallInteger('tier_window_months')->default(12);
            $table->timestamps();
        });

        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->unsignedInteger('threshold_points')->default(0);
            $table->unsignedInteger('earn_multiplier_bp')->default(10000);
            $table->json('benefits')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['restaurant_id', 'threshold_points']);
        });

        Schema::create('loyalty_earn_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 20); // product | category | branch
            $table->unsignedBigInteger('scope_id');
            $table->unsignedInteger('multiplier_bp');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'scope']);
        });

        // Every point movement. Credits are "lots" with what is left of them, spent soonest-expiring first.
        Schema::create('loyalty_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->integer('points');
            $table->unsignedInteger('remaining')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'expires_at']);
            $table->index(['restaurant_id', 'created_at']);
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('type', 20); // free_item | percent_off | fixed_off | bonus_points
            $table->unsignedInteger('points_cost')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('value')->nullable(); // bp for percent, minor for fixed, points for bonus
            $table->unsignedInteger('min_order_minor')->default(0);
            $table->unsignedSmallInteger('valid_days')->default(30);
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('status', 20);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('used_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->integer('points_balance')->default(0);
            $table->foreignId('tier_id')->nullable()->constrained('loyalty_tiers')->nullOnDelete();
        });

        // The old single "points per riyal" setting moves into the program, but only for
        // programs that exist: new restaurants start with no values (owner decides).
        if (Schema::hasColumn('restaurants', 'loyalty_points_per_unit')) {
            foreach (DB::table('loyalty_programs')->get(['id', 'restaurant_id']) as $program) {
                $rate = DB::table('restaurants')->where('id', $program->restaurant_id)->value('loyalty_points_per_unit');
                DB::table('loyalty_programs')->where('id', $program->id)->update(['earn_points_per_unit' => $rate]);
            }

            Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn('loyalty_points_per_unit'));
        }
    }

    public function down(): void
    {
        Schema::table('restaurants', fn (Blueprint $table) => $table->unsignedInteger('loyalty_points_per_unit')->default(1));
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tier_id');
            $table->dropColumn('points_balance');
        });
        Schema::dropIfExists('customer_rewards');
        Schema::dropIfExists('rewards');
        Schema::dropIfExists('loyalty_ledger');
        Schema::dropIfExists('loyalty_earn_rules');
        Schema::dropIfExists('loyalty_tiers');
        Schema::dropIfExists('loyalty_programs');
    }
};
