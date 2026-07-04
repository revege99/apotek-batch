<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Medicine;
use App\Models\PharmacyProfile;
use App\Models\Principal;
use App\Models\Sale;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateOperationalLicense();
    }

    public function test_authenticated_user_can_view_cashier_sale_page(): void
    {
        $user = User::factory()->create();
        [, $customer, $medicine] = $this->seedSellableData();

        $response = $this
            ->actingAs($user)
            ->get(route('penjualan.kasir-penjualan'));

        $response
            ->assertOk()
            ->assertSee('Detail penjualan')
            ->assertSee('Markup %')
            ->assertSee($customer->name)
            ->assertSee($medicine->name)
            ->assertSee('Data Penjualan');
    }

    public function test_authenticated_user_can_view_sales_history_page(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('penjualan.data-penjualan'));

        $response
            ->assertOk()
            ->assertSee('Riwayat penjualan')
            ->assertSee('Kasir Penjualan');
    }

    public function test_cashier_sale_page_groups_duplicate_batch_numbers_for_the_same_medicine(): void
    {
        $user = User::factory()->create();
        [, , $medicine, $stockBatch] = $this->seedSellableData();

        $duplicateBatch = StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => null,
            'batch_number' => $stockBatch->batch_number,
            'expiry_date' => $stockBatch->expiry_date,
            'received_at' => now()->addDay()->toDateString(),
            'purchase_price' => 950,
            'initial_quantity' => 6,
            'quantity_in' => 6,
            'quantity_out' => 0,
            'quantity_balance' => 6,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('penjualan.kasir-penjualan'));

        $response->assertOk();

        $initialForm = $response->viewData('initialForm');
        $medicineRow = collect($initialForm['items'] ?? [])->firstWhere('medicine_id', $medicine->id);

        $this->assertNotNull($medicineRow);
        $this->assertCount(1, $medicineRow['batches']);
        $this->assertSame($medicineRow['stock_batch_id'], $medicineRow['batches'][0]['id']);
        $this->assertEquals(16, (float) $medicineRow['batches'][0]['stock_quantity']);
        $this->assertContains((string) $stockBatch->id, $medicineRow['batches'][0]['stock_batch_ids']);
        $this->assertContains((string) $duplicateBatch->id, $medicineRow['batches'][0]['stock_batch_ids']);
    }

    public function test_sale_transaction_uses_customer_group_markup_and_reduces_stock(): void
    {
        $user = User::factory()->create();
        [$customerGroup, $customer, $medicine, $stockBatch] = $this->seedSellableData();

        $response = $this
            ->actingAs($user)
            ->post(route('penjualan.kasir-penjualan.store'), [
                'sale_number' => 'PJL-0001',
                'sale_date' => now()->format('Y-m-d\TH:i'),
                'customer_id' => $customer->id,
                'payment_method' => 'cash',
                'paid_amount' => 5000,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'stock_batch_id' => $stockBatch->id,
                        'quantity' => 4,
                        'unit_cost' => 1000,
                        'markup_percentage' => 20,
                        'unit_price' => 1200,
                    ],
                ],
            ]);

        $sale = Sale::query()->with('items')->first();

        $response
            ->assertRedirect(route('penjualan.kasir-penjualan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success'
                && str_contains((string) ($toast['message'] ?? ''), 'berhasil disimpan'));

        $this->assertNotNull($sale);
        $this->assertSame('PJL-0001', $sale->sale_number);
        $this->assertEquals($customerGroup->id, $sale->customer_group_id);
        $this->assertEquals(20, (float) $sale->customer_group_markup_percentage);
        $this->assertEquals(4800, (float) $sale->grand_total);
        $this->assertEquals(200, (float) $sale->change_amount);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'stock_batch_id' => $stockBatch->id,
            'quantity' => 4,
            'unit_cost' => 1000,
            'markup_percentage' => 20,
            'unit_price' => 1200,
            'line_total' => 4800,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $stockBatch->id,
            'quantity_in' => 10,
            'quantity_out' => 4,
            'quantity_balance' => 6,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'sale',
            'reference_table' => 'sale_items',
            'medicine_id' => $medicine->id,
            'stock_batch_id' => $stockBatch->id,
            'quantity_out' => 4,
            'balance_after' => 6,
            'unit_cost' => 1000,
        ]);
    }

    public function test_sale_transaction_uses_master_medicine_purchase_price_for_markup_calculation(): void
    {
        $user = User::factory()->create();
        [$customerGroup, $customer, $medicine, $stockBatch] = $this->seedSellableData();

        $medicine->update([
            'purchase_price' => 2000,
        ]);
        $stockBatch->update([
            'purchase_price' => 1000,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('penjualan.kasir-penjualan.store'), [
                'sale_number' => 'PJL-0100',
                'sale_date' => now()->format('Y-m-d\TH:i'),
                'customer_id' => $customer->id,
                'payment_method' => 'cash',
                'paid_amount' => 10000,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'stock_batch_id' => $stockBatch->id,
                        'quantity' => 4,
                        'unit_cost' => 1000,
                        'markup_percentage' => 20,
                        'unit_price' => 1200,
                    ],
                ],
            ]);

        $sale = Sale::query()->where('sale_number', 'PJL-0100')->first();

        $response
            ->assertRedirect(route('penjualan.kasir-penjualan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertNotNull($sale);
        $this->assertEquals($customerGroup->id, $sale->customer_group_id);
        $this->assertEquals(9600, (float) $sale->grand_total);
        $this->assertEquals(400, (float) $sale->change_amount);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'stock_batch_id' => $stockBatch->id,
            'quantity' => 4,
            'unit_cost' => 1000,
            'markup_percentage' => 20,
            'unit_price' => 2400,
            'line_total' => 9600,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'sale',
            'reference_table' => 'sale_items',
            'medicine_id' => $medicine->id,
            'stock_batch_id' => $stockBatch->id,
            'quantity_out' => 4,
            'balance_after' => 6,
            'unit_cost' => 1000,
        ]);
    }

    public function test_sale_transaction_can_override_customer_group_markup_manually(): void
    {
        $user = User::factory()->create();
        [$customerGroup, $customer, $medicine, $stockBatch] = $this->seedSellableData();

        $response = $this
            ->actingAs($user)
            ->post(route('penjualan.kasir-penjualan.store'), [
                'sale_number' => 'PJL-0102',
                'sale_date' => now()->format('Y-m-d\TH:i'),
                'customer_id' => $customer->id,
                'payment_method' => 'cash',
                'paid_amount' => 5000,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'stock_batch_id' => $stockBatch->id,
                        'quantity' => 3,
                        'unit_cost' => 1000,
                        'markup_percentage' => 35,
                        'unit_price' => 1200,
                    ],
                ],
            ]);

        $sale = Sale::query()->where('sale_number', 'PJL-0102')->first();

        $response
            ->assertRedirect(route('penjualan.kasir-penjualan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertNotNull($sale);
        $this->assertEquals($customerGroup->id, $sale->customer_group_id);
        $this->assertEquals(20, (float) $sale->customer_group_markup_percentage);
        $this->assertEquals(4050, (float) $sale->grand_total);
        $this->assertEquals(950, (float) $sale->change_amount);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'stock_batch_id' => $stockBatch->id,
            'quantity' => 3,
            'unit_cost' => 1000,
            'markup_percentage' => 35,
            'unit_price' => 1350,
            'line_total' => 4050,
        ]);
    }

    public function test_sale_transaction_can_split_the_same_medicine_across_multiple_batches(): void
    {
        $user = User::factory()->create();
        [$customerGroup, $customer, $medicine, $firstBatch] = $this->seedSellableData();

        $secondBatch = StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => null,
            'batch_number' => 'BATCH-SAL-02',
            'expiry_date' => now()->addMonths(10)->toDateString(),
            'received_at' => now()->toDateString(),
            'purchase_price' => 1100,
            'initial_quantity' => 8,
            'quantity_in' => 8,
            'quantity_out' => 0,
            'quantity_balance' => 8,
            'status' => 'active',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('penjualan.kasir-penjualan.store'), [
                'sale_number' => 'PJL-0002',
                'sale_date' => now()->format('Y-m-d\TH:i'),
                'customer_id' => $customer->id,
                'payment_method' => 'cash',
                'paid_amount' => 8000,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'stock_batch_id' => $firstBatch->id,
                        'quantity' => 4,
                        'unit_cost' => 1000,
                        'markup_percentage' => 20,
                        'unit_price' => 1200,
                    ],
                    [
                        'medicine_id' => $medicine->id,
                        'stock_batch_id' => $secondBatch->id,
                        'quantity' => 2,
                        'unit_cost' => 1100,
                        'markup_percentage' => 20,
                        'unit_price' => 1320,
                    ],
                ],
            ]);

        $sale = Sale::query()->where('sale_number', 'PJL-0002')->with('items')->first();

        $response
            ->assertRedirect(route('penjualan.kasir-penjualan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertNotNull($sale);
        $this->assertEquals($customerGroup->id, $sale->customer_group_id);
        $this->assertEquals(7200, (float) $sale->grand_total);
        $this->assertEquals(800, (float) $sale->change_amount);
        $this->assertCount(2, $sale->items);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'stock_batch_id' => $firstBatch->id,
            'quantity' => 4,
            'unit_cost' => 1000,
            'unit_price' => 1200,
            'line_total' => 4800,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'medicine_id' => $medicine->id,
            'stock_batch_id' => $secondBatch->id,
            'quantity' => 2,
            'unit_cost' => 1100,
            'unit_price' => 1200,
            'line_total' => 2400,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $firstBatch->id,
            'quantity_out' => 4,
            'quantity_balance' => 6,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $secondBatch->id,
            'quantity_out' => 2,
            'quantity_balance' => 6,
        ]);
    }

    public function test_sale_transaction_can_allocate_grouped_duplicate_batch_stock_from_single_selection(): void
    {
        $user = User::factory()->create();
        [$customerGroup, $customer, $medicine, $firstBatch] = $this->seedSellableData();

        $firstBatch->update([
            'quantity_in' => 4,
            'initial_quantity' => 4,
            'quantity_balance' => 4,
        ]);

        $secondBatch = StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => null,
            'batch_number' => $firstBatch->batch_number,
            'expiry_date' => $firstBatch->expiry_date,
            'received_at' => now()->addDay()->toDateString(),
            'purchase_price' => 1300,
            'initial_quantity' => 6,
            'quantity_in' => 6,
            'quantity_out' => 0,
            'quantity_balance' => 6,
            'status' => 'active',
        ]);

        $createResponse = $this
            ->actingAs($user)
            ->get(route('penjualan.kasir-penjualan'));

        $createResponse->assertOk();

        $initialForm = $createResponse->viewData('initialForm');
        $medicineRow = collect($initialForm['items'] ?? [])->firstWhere('medicine_id', $medicine->id);
        $selectedBatchId = $medicineRow['batches'][0]['id'] ?? null;

        $this->assertNotNull($selectedBatchId);

        $response = $this
            ->actingAs($user)
            ->post(route('penjualan.kasir-penjualan.store'), [
                'sale_number' => 'PJL-0101',
                'sale_date' => now()->format('Y-m-d\TH:i'),
                'customer_id' => $customer->id,
                'payment_method' => 'cash',
                'paid_amount' => 10000,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'stock_batch_id' => $selectedBatchId,
                        'quantity' => 8,
                        'unit_cost' => 1000,
                        'markup_percentage' => 20,
                        'unit_price' => 1200,
                    ],
                ],
            ]);

        $sale = Sale::query()->where('sale_number', 'PJL-0101')->with('items')->first();

        $response
            ->assertRedirect(route('penjualan.kasir-penjualan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertNotNull($sale);
        $this->assertEquals($customerGroup->id, $sale->customer_group_id);
        $this->assertEquals(9600, (float) $sale->grand_total);
        $this->assertEquals(400, (float) $sale->change_amount);
        $this->assertCount(2, $sale->items);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'stock_batch_id' => $firstBatch->id,
            'quantity' => 4,
            'unit_cost' => 1000,
            'unit_price' => 1200,
            'line_total' => 4800,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'stock_batch_id' => $secondBatch->id,
            'quantity' => 4,
            'unit_cost' => 1300,
            'unit_price' => 1200,
            'line_total' => 4800,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $firstBatch->id,
            'quantity_out' => 4,
            'quantity_balance' => 0,
            'status' => 'sold_out',
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $secondBatch->id,
            'quantity_out' => 4,
            'quantity_balance' => 2,
            'status' => 'active',
        ]);
    }

    public function test_sales_history_detail_merges_grouped_duplicate_batch_allocations_into_one_row(): void
    {
        $user = User::factory()->create();
        [$sale, $medicine] = $this->createSaleWithGroupedDuplicateBatchAllocations($user, 'PJL-0201');

        $response = $this
            ->actingAs($user)
            ->get(route('penjualan.data-penjualan'));

        $response->assertOk();

        $detailPayloads = $response->viewData('detailPayloads');
        $detail = $detailPayloads[$sale->id] ?? null;

        $this->assertIsArray($detail);
        $this->assertSame('1', $detail['item_count']);
        $this->assertCount(1, $detail['items']);
        $this->assertSame($medicine->name, $detail['items'][0]['medicine']);
        $this->assertSame('BATCH-SAL-01', $detail['items'][0]['batch_number']);
        $this->assertSame('8 Kapsul', $detail['items'][0]['quantity']);
        $this->assertSame('Rp 9.600', $detail['items'][0]['line_total']);
    }

    public function test_sale_print_groups_duplicate_batch_allocations_into_one_row(): void
    {
        $user = User::factory()->create();
        [$sale, $medicine] = $this->createSaleWithGroupedDuplicateBatchAllocations($user, 'PJL-0202');

        $fakePdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $fakePdf->shouldReceive('setPaper')
            ->once()
            ->with('a4')
            ->andReturnSelf();
        $fakePdf->shouldReceive('download')
            ->once()
            ->with('penjualan-PJL-0202.pdf')
            ->andReturn(response('PDF', 200, [
                'content-type' => 'application/pdf',
                'content-disposition' => 'attachment; filename=penjualan-PJL-0202.pdf',
            ]));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('sales.print', \Mockery::on(function (array $data) use ($sale, $medicine): bool {
                $this->assertSame($sale->id, $data['sale']->id);
                $this->assertCount(1, $data['groupedItems']);
                $this->assertSame($medicine->name, $data['groupedItems'][0]['medicine_name']);
                $this->assertSame('BATCH-SAL-01', $data['groupedItems'][0]['batch_number']);
                $this->assertEquals(8, (float) $data['groupedItems'][0]['quantity']);
                $this->assertEquals(9600, (float) $data['groupedItems'][0]['line_total']);

                return true;
            }))
            ->andReturn($fakePdf);

        $response = $this
            ->actingAs($user)
            ->get(route('penjualan.data-penjualan.print', $sale));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('penjualan-PJL-0202.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_sale_transaction_can_be_saved_as_credit_without_paid_amount(): void
    {
        $user = User::factory()->create();
        [$customerGroup, $customer, $medicine, $stockBatch] = $this->seedSellableData();

        $response = $this
            ->actingAs($user)
            ->post(route('penjualan.kasir-penjualan.store'), [
                'sale_number' => 'PJL-0003',
                'sale_date' => now()->format('Y-m-d\TH:i'),
                'customer_id' => $customer->id,
                'payment_method' => 'credit',
                'paid_amount' => 0,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'stock_batch_id' => $stockBatch->id,
                        'quantity' => 3,
                        'unit_cost' => 1000,
                        'markup_percentage' => 20,
                        'unit_price' => 1200,
                    ],
                ],
            ]);

        $sale = Sale::query()->where('sale_number', 'PJL-0003')->first();

        $response
            ->assertRedirect(route('penjualan.kasir-penjualan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertNotNull($sale);
        $this->assertEquals($customerGroup->id, $sale->customer_group_id);
        $this->assertSame('credit', $sale->payment_method);
        $this->assertEquals(3600, (float) $sale->grand_total);
        $this->assertEquals(0, (float) $sale->paid_amount);
        $this->assertEquals(0, (float) $sale->change_amount);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $stockBatch->id,
            'quantity_out' => 3,
            'quantity_balance' => 7,
        ]);
    }

    public function test_sale_transaction_can_be_saved_as_social_payment_without_creating_receivable(): void
    {
        $user = User::factory()->create();
        [$customerGroup, $customer, $medicine, $stockBatch] = $this->seedSellableData();

        $response = $this
            ->actingAs($user)
            ->post(route('penjualan.kasir-penjualan.store'), [
                'sale_number' => 'PJL-0003A',
                'sale_date' => now()->format('Y-m-d\TH:i'),
                'customer_id' => $customer->id,
                'payment_kind' => 'social',
                'payment_method' => 'cash',
                'paid_amount' => 3000,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'stock_batch_id' => $stockBatch->id,
                        'quantity' => 4,
                        'unit_cost' => 1000,
                        'markup_percentage' => 20,
                        'unit_price' => 1200,
                    ],
                ],
            ]);

        $sale = Sale::query()->where('sale_number', 'PJL-0003A')->first();

        $response
            ->assertRedirect(route('penjualan.kasir-penjualan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertNotNull($sale);
        $this->assertEquals($customerGroup->id, $sale->customer_group_id);
        $this->assertSame('cash', $sale->payment_method);
        $this->assertEquals(4800, (float) $sale->subtotal);
        $this->assertEquals(0, (float) $sale->discount_amount);
        $this->assertEquals(1800, (float) $sale->social_amount);
        $this->assertEquals(4800, (float) $sale->grand_total);
        $this->assertEquals(3000, (float) $sale->paid_amount);
        $this->assertEquals(0, (float) $sale->change_amount);
        $this->assertStringContainsString('Penjualan sosial', (string) $sale->notes);
        $this->assertStringContainsString('Rp 3.000', (string) $sale->notes);
        $this->assertStringContainsString('Rp 1.800', (string) $sale->notes);
        $this->assertStringContainsString('nilai sosial', (string) $sale->notes);
    }

    public function test_sale_history_can_download_pdf_document(): void
    {
        $user = User::factory()->create();
        [, $customer, $medicine, $stockBatch] = $this->seedSellableData();

        $this
            ->actingAs($user)
            ->post(route('penjualan.kasir-penjualan.store'), [
                'sale_number' => 'PJL-0004',
                'sale_date' => now()->format('Y-m-d\TH:i'),
                'customer_id' => $customer->id,
                'payment_method' => 'cash',
                'paid_amount' => 5000,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'stock_batch_id' => $stockBatch->id,
                        'quantity' => 4,
                        'unit_cost' => 1000,
                        'markup_percentage' => 20,
                        'unit_price' => 1200,
                    ],
                ],
            ])
            ->assertRedirect(route('penjualan.kasir-penjualan'));

        $sale = Sale::query()->where('sale_number', 'PJL-0004')->first();

        $this->assertNotNull($sale);

        $response = $this
            ->actingAs($user)
            ->get(route('penjualan.data-penjualan.print', $sale));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('penjualan-PJL-0004.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_sale_can_be_deleted_and_stock_is_restored(): void
    {
        $user = User::factory()->create();
        [, $customer, $medicine, $stockBatch] = $this->seedSellableData();

        $this
            ->actingAs($user)
            ->post(route('penjualan.kasir-penjualan.store'), [
                'sale_number' => 'PJL-0001',
                'sale_date' => now()->format('Y-m-d\TH:i'),
                'customer_id' => $customer->id,
                'payment_method' => 'cash',
                'paid_amount' => 5000,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'stock_batch_id' => $stockBatch->id,
                        'quantity' => 4,
                        'unit_cost' => 1000,
                        'markup_percentage' => 20,
                        'unit_price' => 1200,
                    ],
                ],
            ])
            ->assertRedirect(route('penjualan.kasir-penjualan'));

        $sale = Sale::query()->with('items')->first();
        $this->assertNotNull($sale);
        $saleItemId = $sale->items->first()->id;

        $response = $this
            ->actingAs($user)
            ->delete(route('penjualan.data-penjualan.destroy', $sale));

        $response
            ->assertRedirect(route('penjualan.data-penjualan'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success'
                && str_contains((string) ($toast['message'] ?? ''), 'stok dikembalikan'));

        $this->assertDatabaseMissing('sales', [
            'id' => $sale->id,
        ]);

        $this->assertDatabaseMissing('sale_items', [
            'id' => $saleItemId,
        ]);

        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'sale',
            'reference_table' => 'sale_items',
            'reference_id' => $saleItemId,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $stockBatch->id,
            'quantity_in' => 10,
            'quantity_out' => 0,
            'quantity_balance' => 10,
            'status' => 'active',
        ]);
    }

    /**
     * Seed a sellable medicine, customer group, and customer.
     *
     * @return array{0: CustomerGroup, 1: Customer, 2: Medicine, 3: StockBatch}
     */
    private function seedSellableData(): array
    {
        $principal = Principal::query()->create([
            'code' => 'PRN-SAL-001',
            'name' => 'Kalbe Farma',
            'is_active' => true,
        ]);

        $customerGroup = CustomerGroup::query()->create([
            'code' => 'GPL-0001',
            'name' => 'Klinik',
            'markup_percentage' => 20,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'code' => 'PLG-0001',
            'customer_group_id' => $customerGroup->id,
            'name' => 'Klinik Sehat',
            'phone' => '08123456789',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-SAL-001',
            'name' => 'Amoxicillin 500 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Kapsul',
            'large_unit' => 'Box',
            'small_unit_per_large_unit' => 10,
            'composition' => 'Amoxicillin',
            'purchase_price' => 1000,
            'is_active' => true,
        ]);

        $stockBatch = StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => null,
            'batch_number' => 'BATCH-SAL-01',
            'expiry_date' => now()->addMonths(8)->toDateString(),
            'received_at' => now()->toDateString(),
            'purchase_price' => 1000,
            'initial_quantity' => 10,
            'quantity_in' => 10,
            'quantity_out' => 0,
            'quantity_balance' => 10,
            'status' => 'active',
        ]);

        return [$customerGroup, $customer, $medicine, $stockBatch];
    }

    /**
     * Create a sale that allocates one visible batch across two HPP layers.
     *
     * @return array{0: Sale, 1: Medicine}
     */
    private function createSaleWithGroupedDuplicateBatchAllocations(User $user, string $saleNumber): array
    {
        [, $customer, $medicine, $firstBatch] = $this->seedSellableData();

        $firstBatch->update([
            'quantity_in' => 4,
            'initial_quantity' => 4,
            'quantity_balance' => 4,
        ]);

        StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => null,
            'batch_number' => $firstBatch->batch_number,
            'expiry_date' => $firstBatch->expiry_date,
            'received_at' => now()->addDay()->toDateString(),
            'purchase_price' => 1300,
            'initial_quantity' => 6,
            'quantity_in' => 6,
            'quantity_out' => 0,
            'quantity_balance' => 6,
            'status' => 'active',
        ]);

        $createResponse = $this
            ->actingAs($user)
            ->get(route('penjualan.kasir-penjualan'));

        $createResponse->assertOk();

        $initialForm = $createResponse->viewData('initialForm');
        $medicineRow = collect($initialForm['items'] ?? [])->firstWhere('medicine_id', $medicine->id);
        $selectedBatchId = $medicineRow['batches'][0]['id'] ?? null;

        $this->assertNotNull($selectedBatchId);

        $this
            ->actingAs($user)
            ->post(route('penjualan.kasir-penjualan.store'), [
                'sale_number' => $saleNumber,
                'sale_date' => now()->format('Y-m-d\TH:i'),
                'customer_id' => $customer->id,
                'payment_method' => 'cash',
                'paid_amount' => 10000,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'stock_batch_id' => $selectedBatchId,
                        'quantity' => 8,
                        'unit_cost' => 1000,
                        'markup_percentage' => 20,
                        'unit_price' => 1200,
                    ],
                ],
            ])
            ->assertRedirect(route('penjualan.kasir-penjualan'));

        $sale = Sale::query()->where('sale_number', $saleNumber)->with('items.medicine')->first();

        $this->assertNotNull($sale);
        $this->assertCount(2, $sale->items);

        return [$sale, $medicine];
    }

    private function activateOperationalLicense(string $expiresAt = '2030-12-31'): PharmacyProfile
    {
        return PharmacyProfile::query()->create([
            'name' => 'Apotik Uji',
            'license_number' => 'SIA-UJI',
            'app_license_status' => 'active',
            'app_license_expires_at' => \Illuminate\Support\Carbon::parse($expiresAt)->endOfDay()->toDateTimeString(),
            'is_active' => true,
        ]);
    }
}
