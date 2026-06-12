<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_item_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->decimal('initial_quantity', 12, 2);
            $table->decimal('available_quantity', 12, 2);
            $table->decimal('unit_cost', 12, 4);
            $table->date('received_date');
            $table->date('expiration_date')->nullable();
            $table->string('status', 50)->default('available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
    }
};
