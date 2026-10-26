<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessedMaterialComponent extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory> */
    use HasFactory;

    protected $fillable = ['processed_material_id', 'raw_material_id', 'quantity_per_unit', 'unit', 'position'];

    protected $casts = ['quantity_per_unit' => 'decimal:4'];

    /** @return BelongsTo<Material, $this> */
    public function processedMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'processed_material_id');
    }

    /** @return BelongsTo<Material, $this> */
    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'raw_material_id');
    }
}
