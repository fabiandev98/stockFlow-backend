<?php

namespace Tests\Feature;

use App\Data\Purchase\PurchaseData;
use App\Models\Material;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SupplierService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PurchaseAndSupplierServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_feedback_notes_are_managed_by_the_supplier_service(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Fresh produce']);
        $user = User::factory()->create();
        $service = app(SupplierService::class);

        $note = $service->createFeedbackNote($supplier, [
            'rating' => 'good',
            'note' => 'On-time delivery',
        ], $user);

        $this->assertSame($user->id, $note->user_id);
        $this->assertSame('On-time delivery', $service->feedbackNotes($supplier)->firstOrFail()->note);

        $service->deleteFeedbackNote($supplier, $note);

        $this->assertDatabaseCount('supplier_feedback_notes', 0);
    }

    public function test_a_supplier_cannot_delete_a_note_from_another_supplier(): void
    {
        $supplier = Supplier::query()->create(['name' => 'Fresh produce']);
        $otherSupplier = Supplier::query()->create(['name' => 'Another supplier']);
        $note = app(SupplierService::class)->createFeedbackNote($supplier, ['rating' => 'good'], null);

        $this->expectException(ModelNotFoundException::class);

        app(SupplierService::class)->deleteFeedbackNote($otherSupplier, $note);
    }

    public function test_a_processed_material_cannot_be_added_to_a_purchase(): void
    {
        $user = User::factory()->create();
        $processedMaterial = Material::query()->create([
            'name' => 'French fries',
            'processing_type' => 'processed',
            'unit' => 'kg',
            'minimum_stock' => 0,
            'is_perishable' => true,
        ]);

        try {
            app(PurchaseService::class)->create(new PurchaseData(
                supplier_id: null,
                purchase_date: '2026-10-25',
                notes: null,
                items: [[
                    'material_id' => $processedMaterial->id,
                    'quantity' => 1,
                    'unit_cost' => 1000,
                ]],
            ), $user);

            $this->fail('Processed materials must not be purchasable.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('purchase_items', 0);
    }
}
