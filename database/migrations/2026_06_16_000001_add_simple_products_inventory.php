<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_composed')->default(true)->after('sale_price');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('material_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('material_id')->nullable()->change();
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_item_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->decimal('initial_quantity', 12, 2);
            $table->decimal('available_quantity', 12, 2);
            $table->decimal('unit_cost', 12, 4);
            $table->date('received_date');
            $table->date('expiration_date')->nullable();
            $table->string('status', 50)->default('available');
            $table->timestamps();
        });

        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->decimal('quantity', 12, 2);
            $table->text('reason')->nullable();
            $table->timestamp('movement_date')->useCurrent()->useCurrentOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::dropIfExists('product_batches');

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->foreignId('material_id')->nullable(false)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_composed');
        });
    }
};
