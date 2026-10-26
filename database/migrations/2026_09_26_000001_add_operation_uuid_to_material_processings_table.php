<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_processings', function (Blueprint $table): void {
            $table->uuid('operation_uuid')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('material_processings', function (Blueprint $table): void {
            $table->dropIndex(['operation_uuid']);
            $table->dropColumn('operation_uuid');
        });
    }
};
