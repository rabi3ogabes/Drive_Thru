<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Order-Ready Call: a pager on the guest's own phone.
        Schema::create('pager_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number'); // per branch per day, shown big
            $table->string('label', 60)->nullable();
            $table->string('status', 12); // waiting | ready | collected | expired
            $table->string('phone', 20)->nullable();
            $table->string('locale', 5)->default('ar');
            $table->string('token', 40)->unique(); // the guest's link: one ticket only
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('calls')->default(0);
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('last_called_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('opened_at')->nullable(); // the guest opened the pager page
            $table->dateTime('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        // Counter staff run the pager: cashiers, expeditors and managers by default.
        $positions = DB::table('positions')->whereIn('key', ['general_manager', 'branch_manager', 'cashier', 'expeditor'])->where('is_system', true)->get(['id', 'permissions']);

        foreach ($positions as $position) {
            $permissions = (array) json_decode((string) $position->permissions, true);

            if (! in_array('pager.operate', $permissions, true)) {
                $permissions[] = 'pager.operate';
                DB::table('positions')->where('id', $position->id)->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pager_tickets');
    }
};
