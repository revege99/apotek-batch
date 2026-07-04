<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Principal;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoicePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_purchase_invoice_submission_flashes_error_toast(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('pembelian.input-faktur-pembelian'))
            ->post(route('pembelian.input-faktur-pembelian.store'), [
                'invoice_number' => '',
                'invoice_date' => '',
                'supplier_id' => '',
                'payment_method' => '',
                'tax_percentage' => '',
                'items' => [],
            ]);

        $response
            ->assertRedirect(route('pembelian.input-faktur-pembelian'))
            ->assertSessionHasErrors([
                'invoice_number',
                'invoice_date',
                'supplier_id',
                'payment_method',
                'tax_percentage',
                'items',
            ])
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'error'
                && ($toast['message'] ?? null) === 'Periksa kembali input faktur pembelian. Masih ada data yang perlu diperbaiki.');
    }

    public function test_purchase_history_defaults_to_today_and_paginates_thirty_rows(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::query()->create([
            'code' => 'SUP-BELI-HIST-001',
            'name' => 'PT Supplier Riwayat',
            'is_active' => true,
        ]);

        $this->createPurchaseInvoice($supplier, 'INV-BELI-YESTERDAY', now()->subDay()->toDateString());

        foreach (range(1, 31) as $index) {
            $this->createPurchaseInvoice($supplier, 'INV-BELI-TODAY-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT), now()->toDateString());
        }

        $response = $this
            ->actingAs($user)
            ->get(route('pembelian.data-pembelian'));

        $invoices = $response->viewData('invoices');

        $response
            ->assertOk()
            ->assertSee('Daftar pembelian')
            ->assertDontSee('INV-BELI-YESTERDAY');

        $this->assertSame(now()->toDateString(), $response->viewData('dateFrom'));
        $this->assertSame(now()->toDateString(), $response->viewData('dateTo'));
        $this->assertSame(30, $invoices->perPage());
        $this->assertCount(30, $invoices->items());
        $this->assertSame(31, $invoices->total());
    }

    public function test_purchase_history_detail_invoice_shows_hpp_per_item_from_stock_batch_cost(): void
    {
        $user = User::factory()->create();

        $principal = Principal::query()->create([
            'code' => 'PRN-BELI-HPP-001',
            'name' => 'Tempo Scan',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-BELI-HPP-001',
            'name' => 'PT Supplier HPP',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-BELI-HPP-001',
            'name' => 'Domperidone 10 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'small_unit_per_large_unit' => 10,
            'purchase_price' => 7777,
            'is_active' => true,
        ]);

        $invoiceDate = now()->toDateString();

        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV-BELI-HPP-001',
            'supplier_id' => $supplier->id,
            'invoice_date' => $invoiceDate,
            'received_date' => $invoiceDate,
            'due_date' => null,
            'status' => 'posted',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'subtotal' => 20000,
            'discount_amount' => 0,
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'other_cost_amount' => 0,
            'grand_total' => 20000,
            'paid_amount' => 20000,
            'outstanding_amount' => 0,
            'notes' => null,
            'created_by' => $user->id,
        ]);

        $invoiceItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'storage_location_id' => null,
            'purchase_unit' => 'Tablet',
            'unit_content' => 10,
            'batch_number' => 'BATCH-HPP-01',
            'expiry_date' => now()->addMonths(12)->toDateString(),
            'quantity' => 20,
            'bonus_quantity' => 0,
            'unit_price' => 1000,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 20000,
        ]);

        StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'storage_location_id' => null,
            'batch_number' => 'BATCH-HPP-01',
            'expiry_date' => now()->addMonths(12)->toDateString(),
            'received_at' => $invoiceDate,
            'purchase_price' => 1234.56,
            'selling_price' => 0,
            'initial_quantity' => 20,
            'quantity_in' => 20,
            'quantity_out' => 0,
            'quantity_balance' => 20,
            'status' => 'active',
            'notes' => 'Penerimaan HPP test',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('pembelian.data-pembelian', [
                'date_from' => $invoiceDate,
                'date_to' => $invoiceDate,
            ]));

        $response
            ->assertOk()
            ->assertSee('HPP / Unit')
            ->assertSee('1.234,56')
            ->assertSee('Tablet');

        $invoiceFromView = $response->viewData('invoices')->getCollection()->first();

        $this->assertNotNull($invoiceFromView);
        $this->assertTrue($invoiceFromView->items->first()->relationLoaded('stockBatch'));
        $this->assertEquals(1234.56, (float) $invoiceFromView->items->first()->stockBatch->purchase_price);
    }

    public function test_purchase_invoice_form_shows_manual_unit_content_and_total_qty_columns(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('pembelian.input-faktur-pembelian'));

        $response
            ->assertOk()
            ->assertSee('Isi')
            ->assertSee('Total Qty')
            ->assertSee('qty x isi');
    }

    public function test_purchase_invoice_sets_landed_unit_cost_from_discount_and_tax(): void
    {
        $user = User::factory()->create();

        $principal = Principal::query()->create([
            'code' => 'PRN-BELI-001',
            'name' => 'Kalbe Farma',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-BELI-001',
            'name' => 'PT Supplier Beli',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-BELI-001',
            'name' => 'Paracetamol 500 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'small_unit_per_large_unit' => 10,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('pembelian.input-faktur-pembelian.store'), [
                'invoice_number' => 'INV-BELI-001',
                'invoice_date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'payment_method' => 'cash',
                'tax_percentage' => 11,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'batch_number' => 'BATCH-BELI-01',
                        'expiry_date' => now()->addMonths(12)->toDateString(),
                        'quantity' => 2,
                        'unit_price' => 10000,
                        'discount_percentage' => 10,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                    ],
                ],
            ]);

        $invoice = PurchaseInvoice::query()->first();
        $invoiceItem = PurchaseInvoiceItem::query()->first();
        $stockBatch = StockBatch::query()->first();
        $medicine->refresh();

        $response
            ->assertRedirect(route('pembelian.input-faktur-pembelian'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertNotNull($invoice);
        $this->assertNotNull($invoiceItem);
        $this->assertNotNull($stockBatch);

        $this->assertEquals(20000, (float) $invoice->subtotal);
        $this->assertEquals(2000, (float) $invoice->discount_amount);
        $this->assertEquals(1980, (float) $invoice->tax_amount);
        $this->assertEquals(19980, (float) $invoice->grand_total);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame('cash', $invoice->payment_method);
        $this->assertEquals(19980, (float) $invoice->paid_amount);
        $this->assertEquals(0, (float) $invoice->outstanding_amount);

        $this->assertEquals(10000, (float) $invoiceItem->unit_price);
        $this->assertEquals(2000, (float) $invoiceItem->discount_amount);
        $this->assertEquals(18000, (float) $invoiceItem->line_total);
        $this->assertEquals(1980, (float) $invoiceItem->tax_amount);

        $this->assertEquals(20, (float) $stockBatch->initial_quantity);
        $this->assertEquals(999, (float) $stockBatch->purchase_price);
        $this->assertEquals(999, (float) $medicine->purchase_price);
    }

    public function test_purchase_invoice_can_store_manual_unit_content_and_total_stock_quantity(): void
    {
        $user = User::factory()->create();

        $principal = Principal::query()->create([
            'code' => 'PRN-BELI-001C',
            'name' => 'Novell Pharma',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-BELI-001C',
            'name' => 'PT Supplier Isi Manual',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-BELI-001C',
            'name' => 'Ambroxol 30 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'small_unit_per_large_unit' => 10,
            'purchase_price' => 0,
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('pembelian.input-faktur-pembelian.store'), [
                'invoice_number' => 'INV-BELI-001C',
                'invoice_date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'payment_method' => 'cash',
                'tax_percentage' => 0,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'unit_content' => 12,
                        'batch_number' => 'BATCH-BELI-001C',
                        'expiry_date' => now()->addMonths(12)->toDateString(),
                        'quantity' => 2,
                        'unit_price' => 10000,
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                    ],
                ],
            ]);

        $invoiceItem = PurchaseInvoiceItem::query()->first();
        $stockBatch = StockBatch::query()->first();
        $medicine->refresh();

        $this->assertNotNull($invoiceItem);
        $this->assertNotNull($stockBatch);

        $this->assertEquals(12, (float) $invoiceItem->unit_content);
        $this->assertEquals(24, (float) $stockBatch->initial_quantity);
        $this->assertEquals(24, (float) $stockBatch->quantity_in);
        $this->assertEquals(24, (float) $stockBatch->quantity_balance);
        $this->assertEquals(833.33, (float) $stockBatch->purchase_price);
        $this->assertEquals(833.33, (float) $medicine->purchase_price);
    }

    public function test_purchase_invoice_can_skip_updating_master_purchase_price_when_checkbox_is_unchecked(): void
    {
        $user = User::factory()->create();

        $principal = Principal::query()->create([
            'code' => 'PRN-BELI-001B',
            'name' => 'Indofarma',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-BELI-001B',
            'name' => 'PT Supplier Tanpa Update Master',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-BELI-001B',
            'name' => 'Ibuprofen 200 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'small_unit_per_large_unit' => 10,
            'purchase_price' => 4321,
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('pembelian.input-faktur-pembelian.store'), [
                'invoice_number' => 'INV-BELI-001B',
                'invoice_date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'payment_method' => 'cash',
                'tax_percentage' => 0,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'batch_number' => 'BATCH-BELI-01B',
                        'expiry_date' => now()->addMonths(12)->toDateString(),
                        'quantity' => 1,
                        'unit_price' => 9000,
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                        'update_master_purchase_price' => 0,
                    ],
                ],
            ]);

        $stockBatch = StockBatch::query()->first();
        $medicine->refresh();

        $this->assertNotNull($stockBatch);
        $this->assertEquals(900, (float) $stockBatch->purchase_price);
        $this->assertEquals(4321, (float) $medicine->purchase_price);
    }

    public function test_purchase_invoice_can_store_the_same_medicine_in_multiple_batches(): void
    {
        $user = User::factory()->create();

        $principal = Principal::query()->create([
            'code' => 'PRN-BELI-002',
            'name' => 'Kimia Farma',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-BELI-002',
            'name' => 'PT Supplier Multi Batch',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-BELI-002',
            'name' => 'Amoxicillin 500 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Kapsul',
            'large_unit' => 'Box',
            'small_unit_per_large_unit' => 10,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('pembelian.input-faktur-pembelian.store'), [
                'invoice_number' => 'INV-BELI-002',
                'invoice_date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'payment_method' => 'credit',
                'tax_percentage' => 0,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'batch_number' => 'BATCH-BELI-02-A',
                        'expiry_date' => now()->addMonths(12)->toDateString(),
                        'quantity' => 2,
                        'unit_price' => 10000,
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                    ],
                    [
                        'medicine_id' => $medicine->id,
                        'batch_number' => 'BATCH-BELI-02-B',
                        'expiry_date' => now()->addMonths(15)->toDateString(),
                        'quantity' => 1,
                        'unit_price' => 12000,
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                    ],
                ],
            ]);

        $invoice = PurchaseInvoice::query()->with('items')->where('invoice_number', 'INV-BELI-002')->first();

        $response
            ->assertRedirect(route('pembelian.input-faktur-pembelian'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertNotNull($invoice);
        $this->assertCount(2, $invoice->items);
        $this->assertEquals(32000, (float) $invoice->subtotal);
        $this->assertEquals(32000, (float) $invoice->grand_total);
        $this->assertSame('unpaid', $invoice->payment_status);
        $this->assertSame('credit', $invoice->payment_method);
        $this->assertEquals(0, (float) $invoice->paid_amount);
        $this->assertEquals(32000, (float) $invoice->outstanding_amount);

        $this->assertDatabaseHas('purchase_invoice_items', [
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-BELI-02-A',
            'quantity' => 2,
            'line_total' => 20000,
        ]);

        $this->assertDatabaseHas('purchase_invoice_items', [
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-BELI-02-B',
            'quantity' => 1,
            'line_total' => 12000,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-BELI-02-A',
            'initial_quantity' => 20,
            'quantity_balance' => 20,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-BELI-02-B',
            'initial_quantity' => 10,
            'quantity_balance' => 10,
        ]);
    }

    public function test_purchase_invoice_merges_duplicate_batch_rows_in_the_same_invoice(): void
    {
        $user = User::factory()->create();

        $principal = Principal::query()->create([
            'code' => 'PRN-BELI-004',
            'name' => 'Dexa Medica',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-BELI-005',
            'name' => 'PT Supplier Batch Sama',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-BELI-004',
            'name' => 'Cetirizine 10 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'small_unit_per_large_unit' => 10,
            'is_active' => true,
        ]);

        $expiryDate = now()->addMonths(18)->toDateString();

        $this
            ->actingAs($user)
            ->post(route('pembelian.input-faktur-pembelian.store'), [
                'invoice_number' => 'INV-BELI-005',
                'invoice_date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'payment_method' => 'cash',
                'tax_percentage' => 0,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'batch_number' => '12345',
                        'expiry_date' => $expiryDate,
                        'quantity' => 2,
                        'unit_price' => 10000,
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                    ],
                    [
                        'medicine_id' => $medicine->id,
                        'batch_number' => '12345',
                        'expiry_date' => $expiryDate,
                        'quantity' => 3,
                        'unit_price' => 10000,
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                    ],
                ],
            ]);

        $invoice = PurchaseInvoice::query()->with('items')->where('invoice_number', 'INV-BELI-005')->first();

        $this->assertNotNull($invoice);
        $this->assertCount(1, $invoice->items);
        $this->assertEquals(50000, (float) $invoice->subtotal);
        $this->assertEquals(50000, (float) $invoice->grand_total);

        $this->assertDatabaseHas('purchase_invoice_items', [
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'batch_number' => '12345',
            'quantity' => 5,
            'line_total' => 50000,
        ]);

        $this->assertSame(1, StockBatch::query()->where('medicine_id', $medicine->id)->where('batch_number', '12345')->count());

        $this->assertDatabaseHas('stock_batches', [
            'medicine_id' => $medicine->id,
            'batch_number' => '12345',
            'initial_quantity' => 50,
            'quantity_in' => 50,
            'quantity_balance' => 50,
        ]);
    }

    public function test_purchase_invoice_does_not_merge_duplicate_batch_rows_when_manual_unit_content_differs(): void
    {
        $user = User::factory()->create();

        $principal = Principal::query()->create([
            'code' => 'PRN-BELI-004B',
            'name' => 'Ifars',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-BELI-005B',
            'name' => 'PT Supplier Isi Beda',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-BELI-004B',
            'name' => 'Cetirizine Sirup',
            'principal_id' => $principal->id,
            'small_unit' => 'Botol',
            'large_unit' => 'Dus',
            'small_unit_per_large_unit' => 10,
            'is_active' => true,
        ]);

        $expiryDate = now()->addMonths(18)->toDateString();

        $this
            ->actingAs($user)
            ->post(route('pembelian.input-faktur-pembelian.store'), [
                'invoice_number' => 'INV-BELI-005B',
                'invoice_date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'payment_method' => 'cash',
                'tax_percentage' => 0,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'unit_content' => 10,
                        'batch_number' => 'BATCH-ISI-BEDA',
                        'expiry_date' => $expiryDate,
                        'quantity' => 2,
                        'unit_price' => 10000,
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                    ],
                    [
                        'medicine_id' => $medicine->id,
                        'unit_content' => 12,
                        'batch_number' => 'BATCH-ISI-BEDA',
                        'expiry_date' => $expiryDate,
                        'quantity' => 3,
                        'unit_price' => 10000,
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                    ],
                ],
            ]);

        $invoice = PurchaseInvoice::query()->with('items')->where('invoice_number', 'INV-BELI-005B')->first();

        $this->assertNotNull($invoice);
        $this->assertCount(2, $invoice->items);

        $this->assertDatabaseHas('purchase_invoice_items', [
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-ISI-BEDA',
            'unit_content' => 10,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('purchase_invoice_items', [
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-ISI-BEDA',
            'unit_content' => 12,
            'quantity' => 3,
        ]);

        $this->assertSame(2, StockBatch::query()->where('medicine_id', $medicine->id)->where('batch_number', 'BATCH-ISI-BEDA')->count());
    }

    public function test_purchase_invoice_derives_credit_due_date_and_paid_method_state(): void
    {
        $user = User::factory()->create();

        $principal = Principal::query()->create([
            'code' => 'PRN-BELI-003',
            'name' => 'Sanbe Farma',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-BELI-003',
            'name' => 'PT Supplier Tempo',
            'payment_term_days' => 14,
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-BELI-003',
            'name' => 'Cefixime 100 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Strip',
            'small_unit_per_large_unit' => 10,
            'is_active' => true,
        ]);

        $invoiceDate = now()->toDateString();

        $this
            ->actingAs($user)
            ->post(route('pembelian.input-faktur-pembelian.store'), [
                'invoice_number' => 'INV-BELI-003',
                'invoice_date' => $invoiceDate,
                'supplier_id' => $supplier->id,
                'payment_method' => 'transfer',
                'tax_percentage' => 0,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'batch_number' => 'BATCH-BELI-03-A',
                        'expiry_date' => now()->addMonths(8)->toDateString(),
                        'quantity' => 1,
                        'unit_price' => 15000,
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                    ],
                ],
            ]);

        $this
            ->actingAs($user)
            ->post(route('pembelian.input-faktur-pembelian.store'), [
                'invoice_number' => 'INV-BELI-004',
                'invoice_date' => $invoiceDate,
                'supplier_id' => $supplier->id,
                'payment_method' => 'credit',
                'tax_percentage' => 0,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'batch_number' => 'BATCH-BELI-03-B',
                        'expiry_date' => now()->addMonths(10)->toDateString(),
                        'quantity' => 1,
                        'unit_price' => 18000,
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                    ],
                ],
            ]);

        $paidInvoice = PurchaseInvoice::query()->where('invoice_number', 'INV-BELI-003')->first();
        $creditInvoice = PurchaseInvoice::query()->where('invoice_number', 'INV-BELI-004')->first();

        $this->assertNotNull($paidInvoice);
        $this->assertNotNull($creditInvoice);

        $this->assertSame('paid', $paidInvoice->payment_status);
        $this->assertSame('transfer', $paidInvoice->payment_method);
        $this->assertEquals(15000, (float) $paidInvoice->paid_amount);
        $this->assertEquals(0, (float) $paidInvoice->outstanding_amount);
        $this->assertNull($paidInvoice->due_date);

        $this->assertSame('unpaid', $creditInvoice->payment_status);
        $this->assertSame('credit', $creditInvoice->payment_method);
        $this->assertEquals(0, (float) $creditInvoice->paid_amount);
        $this->assertEquals(18000, (float) $creditInvoice->outstanding_amount);
        $this->assertSame(now()->addDays(14)->toDateString(), $creditInvoice->due_date?->toDateString());
    }

    public function test_deleting_purchase_invoice_preserves_active_history_filter(): void
    {
        $user = User::factory()->create();

        $supplier = Supplier::query()->create([
            'code' => 'SUP-BELI-004',
            'name' => 'PT Supplier Backdate',
            'is_active' => true,
        ]);

        $invoiceDate = now()->subDays(7)->toDateString();
        $invoice = $this->createPurchaseInvoice($supplier, 'INV-BELI-BACKDATE-001', $invoiceDate);

        $response = $this
            ->actingAs($user)
            ->delete(route('pembelian.data-pembelian.destroy', $invoice), [
                'search' => 'BACKDATE',
                'date_from' => $invoiceDate,
                'date_to' => $invoiceDate,
            ]);

        $response
            ->assertRedirect(route('pembelian.data-pembelian', [
                'search' => 'BACKDATE',
                'date_from' => $invoiceDate,
                'date_to' => $invoiceDate,
            ]))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertDatabaseMissing('purchase_invoices', [
            'id' => $invoice->id,
        ]);
    }

    public function test_cash_purchase_invoice_can_be_deleted_when_no_stock_usage_or_supplier_payment_exists(): void
    {
        $user = User::factory()->create();

        $principal = Principal::query()->create([
            'code' => 'PRN-BELI-005',
            'name' => 'Mersifarma',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-BELI-006',
            'name' => 'PT Supplier Tunai',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-BELI-005',
            'name' => 'Loratadine 10 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'small_unit_per_large_unit' => 10,
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('pembelian.input-faktur-pembelian.store'), [
                'invoice_number' => 'INV-BELI-006',
                'invoice_date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'payment_method' => 'cash',
                'tax_percentage' => 0,
                'items' => [
                    [
                        'medicine_id' => $medicine->id,
                        'batch_number' => 'BATCH-HAPUS-TUNAI',
                        'expiry_date' => now()->addMonths(12)->toDateString(),
                        'quantity' => 2,
                        'unit_price' => 10000,
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'discount_mode' => 'percent',
                    ],
                ],
            ]);

        $invoice = PurchaseInvoice::query()->where('invoice_number', 'INV-BELI-006')->first();

        $this->assertNotNull($invoice);
        $this->assertSame('cash', $invoice->payment_method);
        $this->assertEquals(20000, (float) $invoice->paid_amount);

        $response = $this
            ->actingAs($user)
            ->delete(route('pembelian.data-pembelian.destroy', $invoice));

        $response
            ->assertRedirect(route('pembelian.data-pembelian'))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success');

        $this->assertDatabaseMissing('purchase_invoices', [
            'id' => $invoice->id,
        ]);
        $this->assertDatabaseMissing('stock_batches', [
            'batch_number' => 'BATCH-HAPUS-TUNAI',
        ]);
    }

    private function createPurchaseInvoice(Supplier $supplier, string $invoiceNumber, string $invoiceDate): PurchaseInvoice
    {
        return PurchaseInvoice::query()->create([
            'invoice_number' => $invoiceNumber,
            'supplier_id' => $supplier->id,
            'invoice_date' => $invoiceDate,
            'received_date' => $invoiceDate,
            'due_date' => null,
            'status' => 'posted',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'tax_percentage' => 11,
            'tax_amount' => 11000,
            'other_cost_amount' => 0,
            'grand_total' => 111000,
            'paid_amount' => 0,
            'outstanding_amount' => 111000,
            'notes' => null,
            'created_by' => null,
        ]);
    }
}
