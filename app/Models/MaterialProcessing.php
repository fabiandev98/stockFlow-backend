<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialProcessing extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    protected $fillable = [
        'source_material_id', 'output_material_id', 'output_stock_batch_id', 'user_id',
        'operation_uuid',
        'input_quantity', 'output_quantity', 'waste_quantity', 'yield_percentage',
        'total_input_cost', 'output_unit_cost', 'processed_at', 'expiration_date', 'notes',
    ];

    protected $casts = [
        'input_quantity' => 'decimal:4', 'output_quantity' => 'decimal:4', 'waste_quantity' => 'decimal:4',
        'yield_percentage' => 'decimal:4', 'total_input_cost' => 'decimal:4', 'output_unit_cost' => 'decimal:6',
        'processed_at' => 'date', 'expiration_date' => 'date',
    ];

    /** @return BelongsTo<Material, $this> */
    public function sourceMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'source_material_id');
    }

    /** @return BelongsTo<Material, $this> */
    public function outputMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'output_material_id');
    }

    /** @return BelongsTo<StockBatch, $this> */
    public function outputStockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'output_stock_batch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<MaterialProcessingInput, $this> */
    public function inputs(): HasMany
    {
        return $this->hasMany(MaterialProcessingInput::class);
    }
}
