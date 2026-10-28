<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A guest at the counter asking for their stamp; the waiter approves or declines it.
        Schema::create('stamp_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10)->default('pending'); // pending | approved | declined | expired | cancelled
            $table->unsignedSmallInteger('added')->default(0);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_requests');
    }
};
