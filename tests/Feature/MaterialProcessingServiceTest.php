<?php

namespace Tests\Feature;

use App\Data\MaterialProcessing\MaterialProcessingData;
use App\Models\Material;
use App\Models\StockBatch;
use App\Models\User;
use App\Services\MaterialProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialProcessingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_processing_consumes_raw_stock_and_creates_a_processed_batch_with_actual_yield(): void
    {
        $raw = Material::query()->create([
            'name' => 'Whole chicken', 'processing_type' => 'raw', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $processed = Material::query()->create([
            'name' => 'Clean chicken', 'processing_type' => 'processed', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $batch = StockBatch::query()->create([
            'material_id' => $raw->id, 'initial_quantity' => 10, 'available_quantity' => 10,
            'unit_cost' => 5000, 'received_date' => today(), 'status' => 'available',
        ]);

        $processing = app(MaterialProcessingService::class)->create(new MaterialProcessingData(
            output_material_id: $processed->id,
            output_quantity: 8,
            inputs: [['stock_batch_id' => $batch->id, 'quantity' => 10]],
            processed_at: today()->toDateString(),
            expiration_date: null,
            notes: 'Trimmed and cleaned',
        ), User::factory()->create());

        $this->assertSame('0.00', $batch->fresh()->available_quantity);
        $this->assertSame('depleted', $batch->fresh()->status);
        $this->assertSame('80.0000', $processing->yield_percentage);
        $this->assertSame('2.0000', $processing->waste_quantity);
        $this->assertSame('6250.000000', $processing->output_unit_cost);
        $this->assertDatabaseHas('stock_batches', [
            'id' => $processing->output_stock_batch_id,
            'material_id' => $processed->id,
            'available_quantity' => 8,
            'unit_cost' => 6250,
        ]);
        $this->assertDatabaseCount('stock_movements', 2);
    }
}
