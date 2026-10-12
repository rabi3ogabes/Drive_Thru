<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_roles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable()->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('platform_role_id')->references('id')->on('platform_roles')->nullOnDelete();
        });

        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->string('logo_path')->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->char('currency', 3)->default('QAR');
            $table->string('timezone')->default('Asia/Qatar');
            $table->string('default_locale', 5)->default('ar');
            $table->string('status', 20)->default('active')->index();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('key')->nullable();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['restaurant_id', 'key']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_owner')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('restaurants');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['platform_role_id']);
        });
        Schema::dropIfExists('platform_roles');
    }
};
