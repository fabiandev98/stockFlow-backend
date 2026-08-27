<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('status', 20)->default('completed')->after('total_amount');
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by_user_id')
                ->nullable()
                ->after('cancelled_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['status', 'cancelled_at', 'cancellation_reason']);
        });
    }
};
