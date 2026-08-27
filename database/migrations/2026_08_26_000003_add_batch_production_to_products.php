<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('production_mode', 30)->default('on_sale')->after('is_composed');
            $table->unsignedInteger('shelf_life_days')->nullable()->after('production_mode');
        });

        Schema::create('product_productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('planned_quantity', 12, 2)->nullable();
            $table->decimal('produced_quantity', 12, 2);
            $table->date('production_date');
            $table->date('suggested_expiration_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->text('expiration_override_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('product_production_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 4);
            $table->timestamps();
        });

        Schema::table('product_batches', function (Blueprint $table) {
            $table->foreignId('product_production_id')->nullable()->after('purchase_item_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_batches', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_production_id'));
        Schema::dropIfExists('product_production_usages');
        Schema::dropIfExists('product_productions');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['production_mode', 'shelf_life_days']);
        });
    }
};
