<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->decimal('accepted_quantity', 12, 2)->default(0)->after('quantity');
            $table->decimal('rejected_quantity', 12, 2)->default(0)->after('accepted_quantity');
            $table->text('quality_issue_reason')->nullable()->after('rejected_quantity');
        });

        DB::table('purchase_items')->update([
            'accepted_quantity' => DB::raw('quantity'),
        ]);
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->dropColumn(['accepted_quantity', 'rejected_quantity', 'quality_issue_reason']);
        });
    }
};
