<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_material_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('processed_material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('quantity_per_unit', 12, 4);
            $table->string('unit', 50);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['processed_material_id', 'raw_material_id'], 'processed_material_raw_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_material_components');
    }
};
