<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * The platform is now Dhawq. The invoice legal name follows, unless the
 * super admin already set their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Compared in PHP: JSON columns compare differently across SQLite, MySQL and MariaDB.
        $stored = DB::table('platform_settings')->where('key', 'billing.legal_name')->value('value');

        if (is_string($stored) && json_decode(trim($stored), true) === 'Drive-Thru Plus') {
            DB::table('platform_settings')->where('key', 'billing.legal_name')
                ->update(['value' => json_encode('Dhawq'), 'updated_at' => now()]);
            Cache::forget('platform_settings.v1');
        }
    }

    public function down(): void
    {
        // Renaming back is not needed.
    }
};
