<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialProcessingInput extends Model
{
    use HasFactory;

    protected $fillable = ['material_processing_id', 'stock_batch_id', 'quantity', 'unit_cost', 'total_cost'];

    protected $casts = ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:6', 'total_cost' => 'decimal:4'];

    /** @return BelongsTo<MaterialProcessing, $this> */
    public function materialProcessing(): BelongsTo
    {
        return $this->belongsTo(MaterialProcessing::class);
    }

    /** @return BelongsTo<StockBatch, $this> */
    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
