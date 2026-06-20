<?php

namespace App\Services;

use App\Data\MaterialCategory\MaterialCategoryData;
use App\Models\MaterialCategory;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MaterialCategoryService
{
    /**
     * @return QueryBuilder<MaterialCategory>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(MaterialCategory::query()->withCount('materials')->orderBy('name'))
            ->allowedFilters([
                AllowedFilter::callback('global', function (Builder $query, $value) {
                    $query->where('name', 'LIKE', "%$value%");
                }),
            ]);
    }

    public function create(MaterialCategoryData $data): MaterialCategory
    {
        return MaterialCategory::create([
            'name' => $data->name,
        ]);
    }

    public function update(MaterialCategory $materialCategory, MaterialCategoryData $data): MaterialCategory
    {
        $materialCategory->update([
            'name' => $data->name,
        ]);

        return $materialCategory->fresh(['materials']) ?? $materialCategory;
    }

    public function delete(MaterialCategory $materialCategory): void
    {
        if ($materialCategory->materials()->exists()) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                __('errors.material_categories.delete_assigned')
            );
        }

        $materialCategory->delete();
    }
}
