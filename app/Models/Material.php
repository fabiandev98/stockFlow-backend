<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    /**
     * @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory>
     */
    use HasFactory;

    protected $fillable = [
        'material_category_id',
        'name',
        'processing_type',
        'unit',
        'minimum_stock',
        'is_perishable',
        'default_expiration_days',
    ];

    protected $casts = [
        'minimum_stock' => 'decimal:2',
        'is_perishable' => 'boolean',
        'default_expiration_days' => 'integer',
    ];

    /**
     * @return BelongsTo<MaterialCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    /** @return HasMany<RecipeIngredient, $this> */
    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return HasMany<StockBatch, $this>
     */
    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return HasMany<MaterialProcessing, $this> */
    public function sourceProcessings(): HasMany
    {
        return $this->hasMany(MaterialProcessing::class, 'source_material_id');
    }

    /** @return HasMany<MaterialProcessing, $this> */
    public function outputProcessings(): HasMany
    {
        return $this->hasMany(MaterialProcessing::class, 'output_material_id');
    }

    /** @return HasMany<ProcessedMaterialComponent, $this> */
    public function processingComponents(): HasMany
    {
        return $this->hasMany(ProcessedMaterialComponent::class, 'processed_material_id')->orderBy('position');
    }

    /** @return HasMany<ProcessedMaterialComponent, $this> */
    public function usedInProcessedMaterials(): HasMany
    {
        return $this->hasMany(ProcessedMaterialComponent::class, 'raw_material_id');
    }
}
