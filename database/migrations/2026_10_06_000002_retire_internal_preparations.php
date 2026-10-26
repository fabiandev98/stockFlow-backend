<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->where('is_internal_preparation', true)->update(['is_active' => false]);

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_internal_preparation');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_internal_preparation')->default(false)->after('is_composed');
        });
    }
};
