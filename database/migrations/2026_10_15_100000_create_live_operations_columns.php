<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5: live operations. Status times for the screens and the dynamic
 * prep estimate, arrival and opt-in location for guests, kitchen station
 * routing, and a database guard that only accepts known order statuses.
 */
return new class extends Migration
{
    private const STATUSES = ['pending_payment', 'scheduled', 'received', 'preparing', 'ready', 'picked_up', 'cancelled'];

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('placed_at');
            $table->timestamp('preparing_at')->nullable()->after('received_at');
            $table->timestamp('ready_at')->nullable()->after('preparing_at');
            $table->timestamp('picked_up_at')->nullable()->after('ready_at');
            $table->timestamp('on_the_way_at')->nullable()->after('picked_up_at');
            $table->timestamp('arrived_at')->nullable()->after('on_the_way_at');
            $table->string('arrival_source', 10)->nullable()->after('arrived_at');
            $table->timestamp('location_consent_at')->nullable()->after('arrival_source');
            $table->timestamp('location_denied_at')->nullable()->after('location_consent_at');
            $table->decimal('last_latitude', 10, 7)->nullable()->after('location_denied_at');
            $table->decimal('last_longitude', 10, 7)->nullable()->after('last_latitude');
            $table->unsignedInteger('last_distance_m')->nullable()->after('last_longitude');
            $table->timestamp('last_location_at')->nullable()->after('last_distance_m');
            $table->timestamp('approach_alerted_at')->nullable()->after('last_location_at');
            $table->index(['branch_id', 'picked_up_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('station', 20)->nullable()->after('notes');
            $table->timestamp('done_at')->nullable()->after('station');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('station', 20)->nullable()->after('prep_minutes');
        });

        Schema::table('stations', function (Blueprint $table) {
            $table->string('kind', 20)->default('general')->after('name');
        });

        $this->guardStatuses();
    }

    public function down(): void
    {
        $this->dropGuard();

        Schema::table('stations', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('station'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['station', 'done_at']));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'picked_up_at']);
            $table->dropColumn([
                'received_at', 'preparing_at', 'ready_at', 'picked_up_at', 'on_the_way_at', 'arrived_at', 'arrival_source',
                'location_consent_at', 'location_denied_at', 'last_latitude', 'last_longitude', 'last_distance_m',
                'last_location_at', 'approach_alerted_at',
            ]);
        });
    }

    /** The database refuses any status that isn't part of the state machine. */
    private function guardStatuses(): void
    {
        $list = "'".implode("','", self::STATUSES)."'";

        match (DB::getDriverName()) {
            'sqlite' => array_map(fn (string $event) => DB::unprepared(
                "CREATE TRIGGER orders_status_guard_{$event} BEFORE {$event} ON orders
                 WHEN NEW.status NOT IN ({$list})
                 BEGIN SELECT RAISE(ABORT, 'invalid order status'); END;"
            ), ['insert', 'update']),
            'mysql', 'mariadb', 'pgsql' => DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_valid CHECK (status IN ({$list}))"),
            default => null,
        };
    }

    private function dropGuard(): void
    {
        match (DB::getDriverName()) {
            'sqlite' => array_map(fn (string $event) => DB::unprepared("DROP TRIGGER IF EXISTS orders_status_guard_{$event}"), ['insert', 'update']),
            'mysql', 'mariadb' => DB::statement('ALTER TABLE orders DROP CHECK orders_status_valid'),
            'pgsql' => DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_status_valid'),
            default => null,
        };
    }
};
