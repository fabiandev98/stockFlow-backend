<?php

namespace App\Services;

use App\Data\Material\MaterialData;
use App\Models\Material;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MaterialService
{
    /**
     * @return QueryBuilder<Material>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(Material::query()->with('category')->orderBy('name'))
            ->allowedFilters([
                'unit',
                'is_perishable',
                AllowedFilter::exact('processing_type'),
                AllowedFilter::exact('material_category_id'),
                AllowedFilter::callback('global', function (Builder $query, $value) {
                    $query
                        ->where('name', 'LIKE', "%$value%")
                        ->orWhere('unit', 'LIKE', "%$value%")
                        ->orWhereHas('category', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        });
                }),
            ])
            ->allowedSorts([
                'name',
                'unit',
                'minimum_stock',
                'created_at',
                AllowedSort::field('category', 'material_category_id'),
            ]);
    }

    public function create(MaterialData $data): Material
    {
        $material = Material::create([
            'material_category_id' => $data->material_category_id,
            'name' => $data->name,
            'processing_type' => $data->processing_type,
            'unit' => $data->unit,
            'minimum_stock' => $data->minimum_stock,
            'is_perishable' => $data->is_perishable,
            'default_expiration_days' => $data->default_expiration_days,
        ]);

        $this->syncProcessingComponents($material, $data);

        return $material->load(['category', 'processingComponents.rawMaterial']);
    }

    public function update(Material $material, MaterialData $data): Material
    {
        $material->update([
            'material_category_id' => $data->material_category_id,
            'name' => $data->name,
            'processing_type' => $data->processing_type,
            'unit' => $data->unit,
            'minimum_stock' => $data->minimum_stock,
            'is_perishable' => $data->is_perishable,
            'default_expiration_days' => $data->default_expiration_days,
        ]);
        $this->syncProcessingComponents($material, $data);

        return $material->fresh(['category', 'processingComponents.rawMaterial']) ?? $material;
    }

    public function delete(Material $material): void
    {
        if ($material->recipeIngredients()->exists()) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                __('errors.materials.delete_used_in_compositions')
            );
        }

        if ($material->purchaseItems()->exists()) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                __('errors.materials.delete_used_in_purchases')
            );
        }

        if ($material->stockBatches()->exists()) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                __('errors.materials.delete_has_inventory')
            );
        }

        if ($material->stockMovements()->exists()) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                __('errors.materials.delete_has_movements')
            );
        }

        $material->delete();
    }

    private function syncProcessingComponents(Material $material, MaterialData $data): void
    {
        $material->processingComponents()->delete();
        if ($data->processing_type !== 'processed') {
            return;
        }

        foreach ($data->processing_components as $position => $component) {
            $rawMaterial = Material::query()->findOrFail((int) $component['raw_material_id']);
            if ($rawMaterial->processing_type !== 'raw' || $rawMaterial->id === $material->id) {
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Processed material components must be raw materials.');
            }

            $material->processingComponents()->create([
                'raw_material_id' => $rawMaterial->id,
                'quantity_per_unit' => $component['quantity_per_unit'],
                'unit' => $rawMaterial->unit,
                'position' => $position,
            ]);
        }
    }
}
