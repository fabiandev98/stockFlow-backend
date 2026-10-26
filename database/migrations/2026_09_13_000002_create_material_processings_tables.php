<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_processings')) {
            Schema::create('material_processings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('source_material_id')->nullable()->constrained('materials')->nullOnDelete();
                $table->foreignId('output_material_id')->constrained('materials')->restrictOnDelete();
                $table->foreignId('output_stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('input_quantity', 12, 4)->nullable();
                $table->decimal('output_quantity', 12, 4);
                $table->decimal('waste_quantity', 12, 4)->nullable();
                $table->decimal('yield_percentage', 7, 4)->nullable();
                $table->decimal('total_input_cost', 14, 4);
                $table->decimal('output_unit_cost', 14, 6);
                $table->date('processed_at');
                $table->date('expiration_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('material_processing_inputs')) {
            Schema::create('material_processing_inputs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('material_processing_id')->constrained()->cascadeOnDelete();
                $table->foreignId('stock_batch_id')->constrained()->restrictOnDelete();
                $table->decimal('quantity', 12, 4);
                $table->decimal('unit_cost', 14, 6);
                $table->decimal('total_cost', 14, 4);
                $table->timestamps();

                $table->unique(['material_processing_id', 'stock_batch_id'], 'mat_proc_input_batch_unique');
            });
        }

        if (! Schema::hasColumn('stock_batches', 'material_processing_id')) {
            Schema::table('stock_batches', function (Blueprint $table): void {
                $table->foreignId('material_processing_id')->nullable()->after('purchase_item_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('stock_movements', 'material_processing_id')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->foreignId('material_processing_id')->nullable()->after('sale_item_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('material_processing_id');
        });

        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('material_processing_id');
        });

        Schema::dropIfExists('material_processing_inputs');
        Schema::dropIfExists('material_processings');
    }
};
