<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('restaurant_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->unsignedSmallInteger('branches_count')->default(1);
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message')->nullable();
            $table->string('locale', 5)->default('ar');
            $table->string('source', 20)->default('website');
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('follow_up_at')->nullable()->index();
            $table->string('lost_reason')->nullable();
            $table->foreignId('converted_restaurant_id')->nullable()->constrained('restaurants')->nullOnDelete();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('body')->nullable();
            $table->string('level', 20)->default('info');
            $table->string('audience', 20)->default('everyone');
            $table->json('action_label')->nullable();
            $table->string('action_url')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_dismissals', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('dismissed_at');
            $table->primary(['announcement_id', 'user_id']);
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('contact_email')->nullable()->after('legal_name');
            $table->string('contact_phone', 30)->nullable()->after('contact_email');
            $table->string('tax_number', 40)->nullable()->after('contact_phone');
            $table->text('billing_address')->nullable()->after('tax_number');
            $table->text('internal_notes')->nullable();
            $table->json('feature_overrides')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name', 'contact_email', 'contact_phone', 'tax_number', 'billing_address',
                'internal_notes', 'feature_overrides', 'suspended_at', 'suspension_reason',
            ]);
        });
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('user_invitations');
        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('leads');
    }
};
