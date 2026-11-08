<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_themes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->json('name');
            $table->json('description');
            $table->json('spec');
            $table->string('cover_path')->nullable();
            $table->boolean('is_built_in')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->json('menu_theme')->nullable()->after('menu_layout');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('menu_theme');
        });

        Schema::dropIfExists('menu_themes');
    }
};
