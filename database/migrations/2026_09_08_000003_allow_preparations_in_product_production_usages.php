<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_production_usages', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->change();
            $table->foreignId('stock_batch_id')->nullable()->change();
            $table->foreignId('source_product_id')->nullable()->after('stock_batch_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->after('source_product_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_production_usages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_batch_id');
            $table->dropConstrainedForeignId('source_product_id');
            $table->foreignId('stock_batch_id')->nullable(false)->change();
            $table->foreignId('material_id')->nullable(false)->change();
        });
    }
};
