<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    protected $fillable = ['recipe_id', 'material_id', 'net_quantity', 'expected_yield_percentage', 'consumption_quantity', 'unit', 'position'];

    protected $casts = [
        'net_quantity' => 'decimal:4',
        'expected_yield_percentage' => 'decimal:2',
        'consumption_quantity' => 'decimal:4',
    ];

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
