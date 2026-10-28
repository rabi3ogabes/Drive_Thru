<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_security_states', function (Blueprint $table) {
            $table->string('principal', 80)->primary();
            $table->unsignedInteger('failed_count')->default(0);
            $table->dateTime('window_started_at');
            $table->dateTime('last_failed_at');
            $table->timestamp('locked_until')->nullable()->index();
        });

        Schema::create('security_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60)->index();
            $table->string('severity', 20)->default('warning');
            $table->string('attempted_email')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->json('details')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
        Schema::dropIfExists('login_security_states');
    }
};
