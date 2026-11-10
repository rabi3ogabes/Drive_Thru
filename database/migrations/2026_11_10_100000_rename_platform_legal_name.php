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
        $updated = DB::table('platform_settings')
            ->where('key', 'billing.legal_name')
            ->where('value', json_encode('Drive-Thru Plus'))
            ->update(['value' => json_encode('Dhawq'), 'updated_at' => now()]);

        if ($updated > 0) {
            Cache::forget('platform_settings.v1');
        }
    }

    public function down(): void
    {
        // Renaming back is not needed.
    }
};
