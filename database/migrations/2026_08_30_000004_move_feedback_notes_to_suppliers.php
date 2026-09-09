<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_feedback_notes', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->change();
        });

        if (! Schema::hasColumn('purchases', 'supplier_id')) {
            return;
        }

        DB::table('supplier_feedback_notes')
            ->whereNotNull('purchase_id')
            ->orderBy('id')
            ->eachById(function (object $note): void {
                $supplierId = DB::table('purchases')
                    ->where('id', $note->purchase_id)
                    ->value('supplier_id');

                if ($supplierId !== null) {
                    DB::table('supplier_feedback_notes')
                        ->where('id', $note->id)
                        ->update(['supplier_id' => $supplierId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('supplier_feedback_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
