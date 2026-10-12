<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orders pushed to the restaurant's POS: its reference, when it accepted them, or why not.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pos_reference', 80)->nullable()->after('wait_alerted_at');
            $table->timestamp('pos_synced_at')->nullable()->after('pos_reference');
            $table->string('pos_error', 200)->nullable()->after('pos_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['pos_reference', 'pos_synced_at', 'pos_error']));
    }
};
