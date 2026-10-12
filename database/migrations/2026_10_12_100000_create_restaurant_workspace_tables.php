<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: branches (hours, closures, lanes, stations), employee ↔ branch
 * coverage, PINs, paired screens and the restaurant's operating settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('logo_path');
            $table->unsignedInteger('tax_rate_bp')->default(0)->after('default_locale');
            $table->unsignedInteger('service_fee_bp')->default(0)->after('tax_rate_bp');
            $table->string('menu_link_mode', 20)->default('single')->after('service_fee_bp');
            $table->boolean('order_override_enabled')->default(false)->after('menu_link_mode');
            $table->unsignedSmallInteger('cancellation_window_minutes')->default(2)->after('order_override_enabled');
            $table->unsignedInteger('loyalty_points_per_unit')->default(1)->after('cancellation_window_minutes');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->json('name');
            $table->json('city')->nullable();
            $table->json('address')->nullable();
            $table->json('phones')->nullable();
            $table->string('maps_url', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('use_restaurant_logo')->default(true);
            $table->boolean('is_open')->default(true);
            $table->unsignedSmallInteger('avg_prep_minutes')->default(10);
            $table->unsignedSmallInteger('capacity_per_slot')->nullable();
            $table->timestamp('paused_until')->nullable();
            $table->unsignedSmallInteger('busy_extra_minutes')->default(0);
            $table->timestamp('busy_until')->nullable();
            $table->boolean('tracking_enabled')->default(true);
            $table->unsignedSmallInteger('arrival_radius_m')->default(60);
            $table->unsignedSmallInteger('approach_radius_m')->default(1500);
            $table->unsignedSmallInteger('avg_speed_kmh')->default(35);
            $table->unsignedSmallInteger('location_ping_seconds')->default(20);
            $table->boolean('auto_arrival')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['restaurant_id', 'code']);
        });

        Schema::create('branch_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = Sunday … 6 = Saturday (Carbon dayOfWeek)
            $table->time('opens_at');
            $table->time('closes_at'); // earlier than opens_at = closes after midnight
            $table->timestamps();

            $table->index(['branch_id', 'weekday']);
        });

        Schema::create('branch_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->json('reason')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'starts_on', 'ends_on']);
        });

        Schema::create('lanes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('kind', 20)->default('lane');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('all_branches')->default(false)->after('is_owner');
            $table->string('pin_hash')->nullable()->after('all_branches');
        });

        Schema::create('branch_employee', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->primary(['branch_id', 'employee_id']);
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('kind', 20);
            $table->string('pairing_code_hash', 64)->nullable()->unique();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('branch_employee');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['all_branches', 'pin_hash']);
        });
        Schema::dropIfExists('stations');
        Schema::dropIfExists('lanes');
        Schema::dropIfExists('branch_closures');
        Schema::dropIfExists('branch_hours');
        Schema::dropIfExists('branches');
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'tax_rate_bp', 'service_fee_bp', 'menu_link_mode', 'order_override_enabled', 'cancellation_window_minutes', 'loyalty_points_per_unit']);
        });
    }
};
