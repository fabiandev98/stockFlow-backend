<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('usable_quantity', 12, 2)->nullable()->after('rejected_quantity');
            $table->string('processing_waste_reason', 1000)->nullable()->after('usable_quantity');
            $table->timestamp('yield_recorded_at')->nullable()->after('processing_waste_reason');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn(['usable_quantity', 'processing_waste_reason', 'yield_recorded_at']);
        });
    }
};
