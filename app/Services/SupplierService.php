<?php

namespace App\Services;

use App\Data\Supplier\SupplierData;
use App\Models\Supplier;
use App\Models\SupplierFeedbackNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
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

    /** @return Collection<int, SupplierFeedbackNote> */
    public function feedbackNotes(Supplier $supplier): Collection
    {
        return $supplier->feedbackNotes()->with('user')->get();
    }

    /** @param array{rating: string, issue_type?: string|null, note?: string|null} $data */
    public function createFeedbackNote(Supplier $supplier, array $data, ?User $user): SupplierFeedbackNote
    {
        return $supplier->feedbackNotes()->create([
            'rating' => $data['rating'],
            'issue_type' => $data['issue_type'] ?? null,
            'note' => $data['note'] ?? null,
            'user_id' => $user?->id,
        ])->load('user');
    }

    public function deleteFeedbackNote(Supplier $supplier, SupplierFeedbackNote $note): void
    {
        if ($note->supplier_id !== $supplier->id) {
            throw (new ModelNotFoundException)->setModel(SupplierFeedbackNote::class, [$note->id]);
        }

        $note->delete();
    }

    /**
     * @return array<string, float|int>
     */
    public function qualityMetrics(Supplier $supplier): array
    {
        $metrics = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.supplier_id', $supplier->id)
            ->selectRaw('COALESCE(SUM(purchase_items.quantity), 0) as delivered_quantity')
            ->selectRaw('COALESCE(SUM(purchase_items.accepted_quantity), 0) as accepted_quantity')
            ->selectRaw('COALESCE(SUM(purchase_items.rejected_quantity), 0) as rejected_quantity')
            ->selectRaw('COALESCE(SUM(purchase_items.rejected_quantity * purchase_items.unit_cost), 0) as rejected_value')
            ->selectRaw('COALESCE(SUM(CASE WHEN purchase_items.rejected_quantity > 0 THEN 1 ELSE 0 END), 0) as incidents')
            ->first();

        $delivered = (float) ($metrics->delivered_quantity ?? 0);
        $accepted = (float) ($metrics->accepted_quantity ?? 0);

        $feedback = DB::table('supplier_feedback_notes')
            ->where('supplier_feedback_notes.supplier_id', $supplier->id)
            ->selectRaw('COUNT(*) as rated_deliveries')
            ->selectRaw("COALESCE(SUM(CASE WHEN rating = 'good' THEN 1 ELSE 0 END), 0) as good_ratings")
            ->selectRaw("COALESCE(AVG(CASE WHEN rating = 'good' THEN 100 WHEN rating = 'regular' THEN 60 ELSE 0 END), 0) as average_rating")
            ->selectRaw("COALESCE(SUM(CASE WHEN rating != 'good' OR issue_type IS NOT NULL THEN 1 ELSE 0 END), 0) as feedback_incidents")
            ->first();
        $feedbackCount = (int) ($feedback->rated_deliveries ?? 0);
        $goodRatings = (int) ($feedback->good_ratings ?? 0);

        return [
            'delivered_quantity' => $delivered,
            'accepted_quantity' => $accepted,
            'rejected_quantity' => (float) ($metrics->rejected_quantity ?? 0),
            'acceptance_percentage' => $delivered > 0 ? round(($accepted / $delivered) * 100, 2) : 0,
            'rejected_value' => round((float) ($metrics->rejected_value ?? 0), 2),
            'incidents' => (int) ($metrics->incidents ?? 0),
            'rated_deliveries' => $feedbackCount,
            'good_ratings' => $goodRatings,
            'positive_feedback_percentage' => $feedbackCount > 0 ? round(($goodRatings / $feedbackCount) * 100, 2) : 0,
            'average_rating' => round((float) ($feedback->average_rating ?? 0), 2),
            'feedback_incidents' => (int) ($feedback->feedback_incidents ?? 0),
        ];
    }
}
