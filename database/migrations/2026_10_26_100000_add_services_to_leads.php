<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The services a prospect asked about on the website (drive_thru, ready_call, rewards).
        Schema::table('leads', function (Blueprint $table) {
            $table->json('services')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('services'));
    }
};
