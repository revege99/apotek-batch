<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Principal;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_stock_medicine_page(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0001',
            'name' => 'Kalbe Farma',
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'SUP-0001',
            'name' => 'PT Supplier Sehat',
            'is_active' => true,
        ]);
        $location = StorageLocation::query()->create([
            'code' => 'LOC-0001',
            'name' => 'Rak A1',
            'is_active' => true,
        ]);
        $medicine = Medicine::query()->create([
            'code' => 'OBT-0001',
            'name' => 'Paracetamol 500 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'is_active' => true,
        ]);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV-0001',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);
        $invoiceItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-A',
            'quantity' => 10,
            'unit_price' => 5000,
            'line_total' => 50000,
        ]);
        StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-A',
            'received_at' => now()->toDateString(),
            'expiry_date' => now()->addMonths(12)->toDateString(),
            'purchase_price' => 5000,
            'initial_quantity' => 100,
            'quantity_in' => 100,
            'quantity_out' => 0,
            'quantity_balance' => 100,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('stok-batch.stok-obat'));

        $response
            ->assertOk()
            ->assertSee('Stok Obat')
            ->assertSee('Paracetamol 500 mg')
            ->assertSee('OBT-0001')
            ->assertSee('Filter tanggal')
            ->assertSee('Ringkasan periode')
            ->assertDontSee('Live search aktif')
            ->assertDontSee('Total masuk periode')
            ->assertDontSee('Total keluar periode')
            ->assertDontSee('Riwayat perjalanan stok obat berdasarkan kode barang.')
            ->assertDontSee('Saldo batch aktif');
    }

    public function test_authenticated_user_can_view_stock_batch_page(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0002',
            'name' => 'Dexa Medica',
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'SUP-0002',
            'name' => 'PT Batch Jaya',
            'is_active' => true,
        ]);
        $medicine = Medicine::query()->create([
            'code' => 'OBT-0002',
            'name' => 'Amoxicillin 500 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Kapsul',
            'is_active' => true,
        ]);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV-0002',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);
        $invoiceItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-XYZ',
            'quantity' => 12,
            'unit_price' => 7500,
            'line_total' => 90000,
        ]);
        StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'batch_number' => 'BATCH-XYZ',
            'received_at' => now()->toDateString(),
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'purchase_price' => 7500,
            'initial_quantity' => 12,
            'quantity_in' => 12,
            'quantity_out' => 2,
            'quantity_balance' => 10,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('stok-batch.stok-per-batch'));

        $response
            ->assertOk()
            ->assertSee('Stok per Batch')
            ->assertSee('BATCH-XYZ')
            ->assertSee('Amoxicillin 500 mg')
            ->assertSee('INV-0002');
    }

    public function test_stock_batch_page_merges_same_medicine_and_batch_from_multiple_invoices(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0002A',
            'name' => 'Kalbe Farma',
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'SUP-0002A',
            'name' => 'Tri Wira',
            'is_active' => true,
        ]);
        $medicine = Medicine::query()->create([
            'code' => 'OBA0000005',
            'name' => 'Abocat 18 (Gea)',
            'principal_id' => $principal->id,
            'small_unit' => 'Pcs',
            'is_active' => true,
        ]);

        $firstInvoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV001',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);
        $firstItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $firstInvoice->id,
            'medicine_id' => $medicine->id,
            'batch_number' => '12345',
            'quantity' => 100,
            'unit_price' => 999,
            'line_total' => 99900,
        ]);
        StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => $firstItem->id,
            'batch_number' => '12345',
            'received_at' => now()->toDateString(),
            'expiry_date' => now()->toDateString(),
            'purchase_price' => 999,
            'initial_quantity' => 100,
            'quantity_in' => 100,
            'quantity_out' => 0,
            'quantity_balance' => 100,
        ]);

        $secondInvoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV002',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);
        $secondItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $secondInvoice->id,
            'medicine_id' => $medicine->id,
            'batch_number' => '12345',
            'quantity' => 100,
            'unit_price' => 999,
            'line_total' => 99900,
        ]);
        StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => $secondItem->id,
            'batch_number' => '12345',
            'received_at' => now()->toDateString(),
            'expiry_date' => now()->toDateString(),
            'purchase_price' => 999,
            'initial_quantity' => 100,
            'quantity_in' => 100,
            'quantity_out' => 0,
            'quantity_balance' => 100,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('stok-batch.stok-per-batch'));

        /** @var \Illuminate\Pagination\LengthAwarePaginator $rows */
        $rows = $response->viewData('rows');
        $items = collect($rows->items());

        $response
            ->assertOk()
            ->assertSee('Stok per Batch')
            ->assertSee('Abocat 18 (Gea)')
            ->assertSee('12345')
            ->assertSee('2 faktur');

        $this->assertCount(1, $items);
        $this->assertSame('OBA0000005', $items->first()->medicine_code);
        $this->assertSame('12345', $items->first()->batch_number);
        $this->assertEquals(200, (float) $items->first()->quantity_balance);
        $this->assertEquals(199800, (float) $items->first()->stock_value);
    }

    public function test_stock_batch_page_can_filter_batches_by_expiry_window(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0002B',
            'name' => 'Kimia Farma',
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'SUP-0002B',
            'name' => 'PT Expired Cepat',
            'is_active' => true,
        ]);

        $nearMedicine = Medicine::query()->create([
            'code' => 'OBT-EXP-01',
            'name' => 'Omeprazole 20 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Kapsul',
            'is_active' => true,
        ]);

        $farMedicine = Medicine::query()->create([
            'code' => 'OBT-EXP-02',
            'name' => 'Vitamin D3',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'is_active' => true,
        ]);

        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV-EXP-001',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        $nearItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $nearMedicine->id,
            'batch_number' => 'BATCH-DEKAT',
            'quantity' => 20,
            'unit_price' => 3500,
            'line_total' => 70000,
        ]);

        StockBatch::query()->create([
            'medicine_id' => $nearMedicine->id,
            'purchase_invoice_item_id' => $nearItem->id,
            'batch_number' => 'BATCH-DEKAT',
            'received_at' => now()->toDateString(),
            'expiry_date' => now()->addDays(20)->toDateString(),
            'purchase_price' => 3500,
            'initial_quantity' => 20,
            'quantity_in' => 20,
            'quantity_out' => 0,
            'quantity_balance' => 20,
        ]);

        $farItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $farMedicine->id,
            'batch_number' => 'BATCH-JAUH',
            'quantity' => 15,
            'unit_price' => 5000,
            'line_total' => 75000,
        ]);

        StockBatch::query()->create([
            'medicine_id' => $farMedicine->id,
            'purchase_invoice_item_id' => $farItem->id,
            'batch_number' => 'BATCH-JAUH',
            'received_at' => now()->toDateString(),
            'expiry_date' => now()->addMonths(7)->toDateString(),
            'purchase_price' => 5000,
            'initial_quantity' => 15,
            'quantity_in' => 15,
            'quantity_out' => 0,
            'quantity_balance' => 15,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('stok-batch.stok-per-batch', ['expiry_within_months' => 1]));

        $response
            ->assertOk()
            ->assertSee('Omeprazole 20 mg')
            ->assertSee('BATCH-DEKAT')
            ->assertDontSee('Vitamin D3')
            ->assertDontSee('BATCH-JAUH');
    }

    public function test_stock_medicine_search_filters_results(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0003',
            'name' => 'Tempo Scan',
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'SUP-0003',
            'name' => 'PT Farmasi Aman',
            'is_active' => true,
        ]);
        $location = StorageLocation::query()->create([
            'code' => 'LOC-0003',
            'name' => 'Rak B2',
            'is_active' => true,
        ]);

        $cetirizine = Medicine::query()->create([
            'code' => 'OBT-0003',
            'name' => 'Cetirizine 10 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'is_active' => true,
        ]);

        $paracetamol = Medicine::query()->create([
            'code' => 'OBT-0004',
            'name' => 'Paracetamol Syrup',
            'principal_id' => $principal->id,
            'small_unit' => 'Botol',
            'large_unit' => 'Dus',
            'is_active' => true,
        ]);

        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV-0003',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        $cetirizineItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $cetirizine->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-CET',
            'quantity' => 24,
            'unit_price' => 4500,
            'line_total' => 108000,
        ]);

        StockBatch::query()->create([
            'medicine_id' => $cetirizine->id,
            'purchase_invoice_item_id' => $cetirizineItem->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-CET',
            'received_at' => now()->toDateString(),
            'expiry_date' => now()->addMonths(10)->toDateString(),
            'purchase_price' => 4500,
            'initial_quantity' => 24,
            'quantity_in' => 24,
            'quantity_out' => 0,
            'quantity_balance' => 24,
        ]);

        $paracetamolItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $paracetamol->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-PAR',
            'quantity' => 12,
            'unit_price' => 7000,
            'line_total' => 84000,
        ]);

        StockBatch::query()->create([
            'medicine_id' => $paracetamol->id,
            'purchase_invoice_item_id' => $paracetamolItem->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-PAR',
            'received_at' => now()->toDateString(),
            'expiry_date' => now()->addMonths(8)->toDateString(),
            'purchase_price' => 7000,
            'initial_quantity' => 12,
            'quantity_in' => 12,
            'quantity_out' => 0,
            'quantity_balance' => 12,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('stok-batch.stok-obat', ['search' => 'Cetirizine']));

        $response
            ->assertOk()
            ->assertSee('Cetirizine 10 mg')
            ->assertDontSee('Paracetamol Syrup');
    }

    public function test_stock_medicine_page_shows_zero_stock_medicines_by_default_and_can_filter_stock_state(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0004',
            'name' => 'Bernofarm',
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'SUP-0004',
            'name' => 'PT Stok Lengkap',
            'is_active' => true,
        ]);
        $location = StorageLocation::query()->create([
            'code' => 'LOC-0004',
            'name' => 'Rak D4',
            'is_active' => true,
        ]);

        $stockedMedicine = Medicine::query()->create([
            'code' => 'OBT-0100',
            'name' => 'Vitamin C 500 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'minimum_stock' => 10,
            'is_active' => true,
        ]);

        $lowStockMedicine = Medicine::query()->create([
            'code' => 'OBT-0102',
            'name' => 'Antasida Syrup',
            'principal_id' => $principal->id,
            'small_unit' => 'Botol',
            'large_unit' => 'Dus',
            'minimum_stock' => 8,
            'is_active' => true,
        ]);

        $emptyMedicine = Medicine::query()->create([
            'code' => 'OBT-0101',
            'name' => 'Zinc Sirup',
            'principal_id' => $principal->id,
            'small_unit' => 'Botol',
            'large_unit' => 'Dus',
            'minimum_stock' => 5,
            'is_active' => true,
        ]);

        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV-0004',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        $invoiceItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $stockedMedicine->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-STOK',
            'quantity' => 30,
            'unit_price' => 6000,
            'line_total' => 180000,
        ]);

        StockBatch::query()->create([
            'medicine_id' => $stockedMedicine->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-STOK',
            'received_at' => now()->toDateString(),
            'expiry_date' => now()->addMonths(9)->toDateString(),
            'purchase_price' => 6000,
            'initial_quantity' => 30,
            'quantity_in' => 30,
            'quantity_out' => 0,
            'quantity_balance' => 30,
        ]);

        $lowStockInvoiceItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $lowStockMedicine->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-MINIM',
            'quantity' => 5,
            'unit_price' => 4000,
            'line_total' => 20000,
        ]);

        StockBatch::query()->create([
            'medicine_id' => $lowStockMedicine->id,
            'purchase_invoice_item_id' => $lowStockInvoiceItem->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-MINIM',
            'received_at' => now()->toDateString(),
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'purchase_price' => 4000,
            'initial_quantity' => 5,
            'quantity_in' => 5,
            'quantity_out' => 0,
            'quantity_balance' => 5,
        ]);

        $this->actingAs($user)
            ->get(route('stok-batch.stok-obat'))
            ->assertOk()
            ->assertSee('Vitamin C 500 mg')
            ->assertSee('Antasida Syrup')
            ->assertSee('Zinc Sirup');

        $this->actingAs($user)
            ->get(route('stok-batch.stok-obat', ['stock_state' => 'available']))
            ->assertOk()
            ->assertSee('Vitamin C 500 mg')
            ->assertSee('Antasida Syrup')
            ->assertDontSee('Zinc Sirup');

        $this->actingAs($user)
            ->get(route('stok-batch.stok-obat', ['stock_state' => 'empty']))
            ->assertOk()
            ->assertSee('Zinc Sirup')
            ->assertDontSee('Antasida Syrup')
            ->assertDontSee('Vitamin C 500 mg');

        $this->actingAs($user)
            ->get(route('stok-batch.stok-obat', ['stock_state' => 'low']))
            ->assertOk()
            ->assertSee('Antasida Syrup')
            ->assertDontSee('Vitamin C 500 mg')
            ->assertDontSee('Zinc Sirup');
    }

    public function test_stock_card_keeps_receipt_and_return_history_as_separate_rows(): void
    {
        $user = User::factory()->create();
        $principal = Principal::query()->create([
            'code' => 'PRN-0005',
            'name' => 'Kalbe Farma',
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'SUP-0005',
            'name' => 'PT Riwayat Obat',
            'is_active' => true,
        ]);
        $location = StorageLocation::query()->create([
            'code' => 'LOC-0005',
            'name' => 'Rak C1',
            'is_active' => true,
        ]);
        $medicine = Medicine::query()->create([
            'code' => 'OBT-0005',
            'name' => 'Ibuprofen 200 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'is_active' => true,
        ]);
        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV-KARTU-001',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);
        $invoiceItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-KARTU',
            'quantity' => 50,
            'unit_content' => 10,
            'unit_price' => 1000,
            'line_total' => 50000,
        ]);
        $stockBatch = StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'storage_location_id' => $location->id,
            'batch_number' => 'BATCH-KARTU',
            'received_at' => now()->toDateString(),
            'expiry_date' => now()->addMonths(12)->toDateString(),
            'purchase_price' => 100,
            'initial_quantity' => 500,
            'quantity_in' => 500,
            'quantity_out' => 100,
            'quantity_balance' => 400,
        ]);

        $purchaseReturn = PurchaseReturn::query()->create([
            'return_number' => 'RTB-KARTU-001',
            'purchase_invoice_id' => $invoice->id,
            'supplier_id' => $supplier->id,
            'return_date' => now()->toDateString(),
            'subtotal' => 10000,
            'tax_amount' => 0,
            'total_amount' => 10000,
            'created_by' => $user->id,
        ]);

        $returnItem = PurchaseReturnItem::query()->create([
            'purchase_return_id' => $purchaseReturn->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'BATCH-KARTU',
            'expiry_date' => now()->addMonths(12)->toDateString(),
            'quantity' => 100,
            'unit_price' => 100,
            'line_total' => 10000,
        ]);

        StockMovement::query()->create([
            'movement_date' => now()->subDay(),
            'movement_type' => 'purchase_receipt',
            'reference_table' => 'purchase_invoice_items',
            'reference_id' => $invoiceItem->id,
            'medicine_id' => $medicine->id,
            'stock_batch_id' => $stockBatch->id,
            'storage_location_id' => $location->id,
            'quantity_in' => 500,
            'quantity_out' => 0,
            'balance_after' => 500,
            'unit_cost' => 100,
            'created_by' => $user->id,
        ]);

        StockMovement::query()->create([
            'movement_date' => now(),
            'movement_type' => 'purchase_return',
            'reference_table' => 'purchase_return_items',
            'reference_id' => $returnItem->id,
            'medicine_id' => $medicine->id,
            'stock_batch_id' => $stockBatch->id,
            'storage_location_id' => $location->id,
            'quantity_in' => 0,
            'quantity_out' => 100,
            'balance_after' => 400,
            'unit_cost' => 100,
            'created_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('stok-batch.stok-obat'));

        $response
            ->assertOk()
            ->assertSee('RTB-KARTU-001')
            ->assertSee('INV-KARTU-001')
            ->assertSee('Retur Pembelian')
            ->assertSee('Masuk')
            ->assertSee('Keluar');
    }
}
