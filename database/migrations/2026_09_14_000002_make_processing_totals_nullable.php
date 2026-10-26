<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_processings', function (Blueprint $table): void {
            $table->foreignId('source_material_id')->nullable()->change();
            $table->decimal('input_quantity', 12, 4)->nullable()->change();
            $table->decimal('waste_quantity', 12, 4)->nullable()->change();
            $table->decimal('yield_percentage', 7, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('material_processings', function (Blueprint $table): void {
            $table->foreignId('source_material_id')->nullable(false)->change();
            $table->decimal('input_quantity', 12, 4)->nullable(false)->change();
            $table->decimal('waste_quantity', 12, 4)->nullable(false)->change();
            $table->decimal('yield_percentage', 7, 4)->nullable(false)->change();
        });
    }
};
