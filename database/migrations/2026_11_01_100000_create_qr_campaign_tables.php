<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A tracked QR code (/q/{code}): where it was put and what it opens.
        Schema::create('qr_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('placement', 60)->nullable(); // e.g. "Car window sticker", "Instagram"
            $table->string('lane', 20)->nullable();
            $table->string('promo', 40)->nullable();
            $table->string('code', 6)->unique();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['restaurant_id', 'is_active']);
        });

        // One scan: a device (salted hash of IP and browser, never the raw values).
        Schema::create('qr_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qr_campaign_id')->constrained()->cascadeOnDelete();
            $table->char('device_hash', 64);
            $table->boolean('is_bot')->default(false);
            $table->dateTime('scanned_at');
            $table->dateTime('visited_at')->nullable();

            $table->index(['qr_campaign_id', 'device_hash', 'scanned_at']);
            $table->index(['qr_campaign_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_scans');
        Schema::dropIfExists('qr_campaigns');
    }
};
