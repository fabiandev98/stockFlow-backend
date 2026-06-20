<?php

namespace App\Services;

use App\Data\Supplier\SupplierData;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SupplierService
{
    /**
     * @return QueryBuilder<Supplier>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(Supplier::query()->withCount('purchases')->orderBy('name'))
            ->allowedFilters([
                AllowedFilter::callback('global', function (Builder $query, $value) {
                    $query
                        ->where('name', 'LIKE', "%$value%")
                        ->orWhere('contact_name', 'LIKE', "%$value%")
                        ->orWhere('phone', 'LIKE', "%$value%")
                        ->orWhere('email', 'LIKE', "%$value%");
                }),
            ]);
    }

    public function create(SupplierData $data): Supplier
    {
        return Supplier::create([
            'name' => $data->name,
            'contact_name' => $data->contact_name,
            'phone' => $data->phone,
            'email' => $data->email,
        ]);
    }

    public function update(Supplier $supplier, SupplierData $data): Supplier
    {
        $supplier->update([
            'name' => $data->name,
            'contact_name' => $data->contact_name,
            'phone' => $data->phone,
            'email' => $data->email,
        ]);

        return $supplier->fresh(['purchases']) ?? $supplier;
    }

    public function delete(Supplier $supplier): void
    {
        if ($supplier->purchases()->exists()) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                __('errors.suppliers.delete_assigned')
            );
        }

        $supplier->delete();
    }
}
