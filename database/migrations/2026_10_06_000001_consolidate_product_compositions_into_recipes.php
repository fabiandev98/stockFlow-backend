<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_compositions')) {
            DB::table('product_compositions')->select('product_id')->distinct()->orderBy('product_id')->each(function (object $row): void {
                $hasActiveRecipe = DB::table('recipes')->where('product_id', $row->product_id)->where('status', 'active')->exists();
                if ($hasActiveRecipe) {
                    return;
                }

                $compositions = DB::table('product_compositions')->where('product_id', $row->product_id)->orderBy('id')->get();
                if ($compositions->isEmpty()) {
                    return;
                }

                $now = now();
                $version = (int) (DB::table('recipes')->where('product_id', $row->product_id)->max('version') ?? 0) + 1;
                $recipeId = DB::table('recipes')->insertGetId([
                    'product_id' => $row->product_id,
                    'version' => $version,
                    'status' => 'active',
                    'yield_quantity' => 1,
                    'yield_unit' => 'u',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($compositions as $position => $composition) {
                    DB::table('recipe_ingredients')->insert([
                        'recipe_id' => $recipeId,
                        'material_id' => $composition->material_id,
                        'net_quantity' => $composition->quantity_required,
                        'unit' => $composition->unit,
                        'position' => $position,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
        }

        Schema::table('product_productions', function (Blueprint $table): void {
            $table->foreignId('recipe_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        Schema::dropIfExists('product_compositions');
    }

    public function down(): void
    {
        Schema::create('product_compositions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_required', 12, 2);
            $table->string('unit', 50);
            $table->timestamps();
        });

        Schema::table('product_productions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recipe_id');
        });
    }
};
