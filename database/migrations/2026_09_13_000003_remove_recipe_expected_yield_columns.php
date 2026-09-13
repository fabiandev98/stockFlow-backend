<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table): void {
            $table->dropColumn(['expected_yield_percentage', 'consumption_quantity']);
        });
    }

    public function down(): void
    {
        Schema::table('recipe_ingredients', function (Blueprint $table): void {
            $table->decimal('expected_yield_percentage', 5, 2)->default(100)->after('net_quantity');
            $table->decimal('consumption_quantity', 12, 4)->default(0)->after('expected_yield_percentage');
        });
    }
};
