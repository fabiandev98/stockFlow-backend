<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedSmallInteger('covers')->nullable()->after('sale_date');
            $table->decimal('subtotal_amount', 12, 2)->default(0)->after('covers');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal_amount');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount_amount');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
        });

        DB::table('sales')->update([
            'subtotal_amount' => DB::raw('total_amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'covers',
                'subtotal_amount',
                'discount_amount',
                'tax_rate',
                'tax_amount',
            ]);
        });
    }
};
