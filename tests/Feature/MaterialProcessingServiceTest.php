<?php

namespace Tests\Feature;

use App\Data\MaterialProcessing\MaterialProcessingData;
use App\Http\Resources\MaterialResource;
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
        $processed->processingComponents()->create([
            'raw_material_id' => $raw->id,
            'quantity_per_unit' => 1.25,
            'unit' => 'kg',
        ]);
        $batch = StockBatch::query()->create([
            'material_id' => $raw->id, 'initial_quantity' => 10, 'available_quantity' => 10,
            'unit_cost' => 5000, 'received_date' => today(), 'status' => 'available',
        ]);

        $processing = app(MaterialProcessingService::class)->create(new MaterialProcessingData(
            output_material_id: $processed->id,
            output_quantity: 8,
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

    public function test_processed_material_resource_serializes_its_raw_material_components(): void
    {
        $raw = Material::query()->create([
            'name' => 'Potato', 'processing_type' => 'raw', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $processed = Material::query()->create([
            'name' => 'French fries', 'processing_type' => 'processed', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $processed->processingComponents()->create([
            'raw_material_id' => $raw->id,
            'quantity_per_unit' => 1.2,
            'unit' => 'kg',
        ]);

        $resource = (new MaterialResource($processed->load('processingComponents.rawMaterial')))
            ->toArray(request());

        $this->assertSame('Potato', $resource['processing_components'][0]['raw_material']['name']);
    }

    public function test_processing_uses_the_selected_batch_and_actual_quantity_when_it_exceeds_the_recipe(): void
    {
        $raw = Material::query()->create([
            'name' => 'Potato', 'processing_type' => 'raw', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $processed = Material::query()->create([
            'name' => 'French fries', 'processing_type' => 'processed', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $processed->processingComponents()->create([
            'raw_material_id' => $raw->id,
            'quantity_per_unit' => 1,
            'unit' => 'kg',
        ]);
        $fefoBatch = StockBatch::query()->create([
            'material_id' => $raw->id, 'initial_quantity' => 10, 'available_quantity' => 10,
            'unit_cost' => 1000, 'received_date' => today(), 'status' => 'available',
        ]);
        $selectedBatch = StockBatch::query()->create([
            'material_id' => $raw->id, 'initial_quantity' => 12, 'available_quantity' => 12,
            'unit_cost' => 1500, 'received_date' => today(), 'status' => 'available',
        ]);

        $processing = app(MaterialProcessingService::class)->create(new MaterialProcessingData(
            output_material_id: $processed->id,
            output_quantity: 8,
            processed_at: today()->toDateString(),
            expiration_date: null,
            notes: null,
            inputs: [['stock_batch_id' => $selectedBatch->id, 'quantity' => 12]],
        ), User::factory()->create());

        $this->assertSame('10.00', $fefoBatch->fresh()->available_quantity);
        $this->assertSame('0.00', $selectedBatch->fresh()->available_quantity);
        $this->assertSame('66.6667', $processing->yield_percentage);
        $this->assertSame('2250.000000', $processing->output_unit_cost);
    }

    public function test_processing_allows_an_omitted_raw_material_when_other_actual_inputs_are_selected(): void
    {
        $potato = Material::query()->create([
            'name' => 'Potato', 'processing_type' => 'raw', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $lettuce = Material::query()->create([
            'name' => 'Lettuce', 'processing_type' => 'raw', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $processed = Material::query()->create([
            'name' => 'Prepared garnish', 'processing_type' => 'processed', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $processed->processingComponents()->createMany([
            ['raw_material_id' => $potato->id, 'quantity_per_unit' => 1, 'unit' => 'kg'],
            ['raw_material_id' => $lettuce->id, 'quantity_per_unit' => 0.25, 'unit' => 'kg'],
        ]);
        $potatoBatch = StockBatch::query()->create([
            'material_id' => $potato->id, 'initial_quantity' => 2, 'available_quantity' => 2,
            'unit_cost' => 1000, 'received_date' => today(), 'status' => 'available',
        ]);

        $processing = app(MaterialProcessingService::class)->create(new MaterialProcessingData(
            output_material_id: $processed->id,
            output_quantity: 1,
            processed_at: today()->toDateString(),
            expiration_date: null,
            notes: 'Lettuce omitted for this batch',
            inputs: [['stock_batch_id' => $potatoBatch->id, 'quantity' => 1]],
        ), User::factory()->create());

        $this->assertSame('1.00', $potatoBatch->fresh()->available_quantity);
        $this->assertCount(1, $processing->inputs);
        $this->assertSame($potato->id, $processing->inputs->firstOrFail()->stockBatch->material_id);
    }

    public function test_grouped_processing_is_atomic_when_one_item_cannot_consume_the_selected_stock(): void
    {
        $raw = Material::query()->create([
            'name' => 'Potato', 'processing_type' => 'raw', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $firstOutput = Material::query()->create([
            'name' => 'Mashed potato', 'processing_type' => 'processed', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $secondOutput = Material::query()->create([
            'name' => 'French fries', 'processing_type' => 'processed', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        foreach ([$firstOutput, $secondOutput] as $output) {
            $output->processingComponents()->create([
                'raw_material_id' => $raw->id, 'quantity_per_unit' => 1, 'unit' => 'kg',
            ]);
        }
        $batch = StockBatch::query()->create([
            'material_id' => $raw->id, 'initial_quantity' => 1, 'available_quantity' => 1,
            'unit_cost' => 1000, 'received_date' => today(), 'status' => 'available',
        ]);

        try {
            app(MaterialProcessingService::class)->createMany([
                new MaterialProcessingData($firstOutput->id, 1, today()->toDateString(), null, null, [['stock_batch_id' => $batch->id, 'quantity' => 1]]),
                new MaterialProcessingData($secondOutput->id, 1, today()->toDateString(), null, null, [['stock_batch_id' => $batch->id, 'quantity' => 1]]),
            ], User::factory()->create());
            $this->fail('The grouped processing should reject unavailable stock.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            $this->assertSame('1.00', $batch->fresh()->available_quantity);
            $this->assertDatabaseCount('material_processings', 0);
        }
    }

    public function test_automatic_fefo_allocation_uses_the_processing_date_to_validate_expiration(): void
    {
        $raw = Material::query()->create([
            'name' => 'Potato', 'processing_type' => 'raw', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $processed = Material::query()->create([
            'name' => 'French fries', 'processing_type' => 'processed', 'unit' => 'kg', 'minimum_stock' => 0,
        ]);
        $processed->processingComponents()->create([
            'raw_material_id' => $raw->id, 'quantity_per_unit' => 1, 'unit' => 'kg',
        ]);
        StockBatch::query()->create([
            'material_id' => $raw->id,
            'initial_quantity' => 2,
            'available_quantity' => 2,
            'unit_cost' => 1000,
            'received_date' => today()->subDays(5),
            'expiration_date' => today()->subDay(),
            'status' => 'available',
        ]);

        $processing = app(MaterialProcessingService::class)->create(new MaterialProcessingData(
            output_material_id: $processed->id,
            output_quantity: 1,
            processed_at: today()->subDays(2)->toDateString(),
            expiration_date: null,
            notes: null,
        ), User::factory()->create());

        $this->assertSame('1.0000', $processing->inputs->firstOrFail()->quantity);
    }
}
