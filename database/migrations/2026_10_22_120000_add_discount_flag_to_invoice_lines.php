<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A discount line (the bundle discount) is subtracted; its amount stays positive.
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->boolean('is_discount')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', fn (Blueprint $table) => $table->dropColumn('is_discount'));
    }
};
