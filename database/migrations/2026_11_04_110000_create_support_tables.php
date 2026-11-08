<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A guest's "report a problem" about a picked-up order, and how it was put right.
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number'); // shown as T-1001, per restaurant
            $table->string('category', 24);
            $table->json('items')->nullable(); // order_item ids the guest pointed at
            $table->string('status', 12)->default('open'); // open | answered | resolved
            $table->string('outcome', 12)->nullable(); // refund | reward
            $table->foreignId('refund_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_reward_id')->nullable()->constrained('customer_rewards')->nullOnDelete();
            $table->dateTime('first_reply_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['restaurant_id', 'number']);
            $table->index(['restaurant_id', 'status']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('author', 8); // guest | staff
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->foreignId('message_delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('created_at');

            $table->index(['support_ticket_id', 'id']);
        });

        // Managers of restaurants created before tickets existed get "Handle support" too.
        DB::table('positions')->whereIn('key', ['general_manager', 'branch_manager'])->orderBy('id')->each(function (object $position): void {
            $permissions = json_decode((string) $position->permissions, true) ?: [];

            if (! in_array('support.handle', $permissions, true)) {
                $permissions[] = 'support.handle';
                DB::table('positions')->where('id', $position->id)->update(['permissions' => json_encode($permissions)]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
