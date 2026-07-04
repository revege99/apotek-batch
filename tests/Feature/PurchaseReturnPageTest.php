<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Principal;
use App\Models\PharmacyProfile;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReturnPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateOperationalLicense();
    }

    public function test_authenticated_user_can_view_purchase_return_page(): void
    {
        $user = User::factory()->create();
        [$invoice] = $this->seedReturnableInvoice($user);

        $response = $this
            ->actingAs($user)
            ->get(route('pembelian.retur-pembelian', ['purchase_invoice_id' => $invoice->id]));

        $response
            ->assertOk()
            ->assertSee('Retur Pembelian')
            ->assertSee('Form retur pembelian')
            ->assertSee($invoice->invoice_number);
    }

    public function test_authenticated_user_can_view_purchase_return_history_page(): void
    {
        $user = User::factory()->create();
        [$invoice] = $this->seedReturnableInvoice($user);

        $response = $this
            ->actingAs($user)
            ->get(route('pembelian.riwayat-retur-pembelian', ['purchase_invoice_id' => $invoice->id]));

        $response
            ->assertOk()
            ->assertSee('Riwayat Retur Pembelian')
            ->assertSee('Riwayat retur pembelian')
            ->assertSee('Form Retur')
            ->assertDontSee('Form retur pembelian');
    }

    public function test_authenticated_user_can_view_exchange_page(): void
    {
        $user = User::factory()->create();
        [$invoice] = $this->seedReturnableInvoice($user);

        $response = $this
            ->actingAs($user)
            ->get(route('pembelian.tukar-barang', ['purchase_invoice_id' => $invoice->id]));

        $response
            ->assertOk()
            ->assertSee('Tukar Barang')
            ->assertSee('Form Tukar barang')
            ->assertSee($invoice->invoice_number);
    }

    public function test_purchase_return_reduces_stock_and_calculates_tax(): void
    {
        $user = User::factory()->create();
        [$invoice, $invoiceItem, $stockBatch] = $this->seedReturnableInvoice($user);

        $response = $this
            ->actingAs($user)
            ->post(route('pembelian.retur-pembelian.store'), [
                'purchase_invoice_id' => $invoice->id,
                'return_date' => now()->toDateString(),
                'notes' => 'Kemasan penyok',
                'items' => [
                    [
                        'purchase_invoice_item_id' => $invoiceItem->id,
                        'quantity' => 10,
                        'reason' => 'Kemasan penyok',
                    ],
                ],
            ]);

        $purchaseReturn = PurchaseReturn::query()->first();

        $response
            ->assertRedirect(route('pembelian.retur-pembelian', ['purchase_invoice_id' => $invoice->id]))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success'
                && str_contains((string) ($toast['message'] ?? ''), 'berhasil disimpan'));

        $this->assertNotNull($purchaseReturn);
        $this->assertSame('RTB-0001', $purchaseReturn->return_number);
        $this->assertEquals(25000, (float) $purchaseReturn->subtotal);
        $this->assertEquals(2750, (float) $purchaseReturn->tax_amount);
        $this->assertEquals(27750, (float) $purchaseReturn->total_amount);

        $this->assertDatabaseHas('purchase_return_items', [
            'purchase_return_id' => $purchaseReturn->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'medicine_id' => $stockBatch->medicine_id,
            'quantity' => 10,
            'unit_price' => 2500,
            'line_total' => 25000,
            'reason' => 'Kemasan penyok',
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $stockBatch->id,
            'quantity_out' => 10,
            'quantity_balance' => 30,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'purchase_return',
            'reference_table' => 'purchase_return_items',
            'medicine_id' => $stockBatch->medicine_id,
            'stock_batch_id' => $stockBatch->id,
            'quantity_out' => 10,
            'balance_after' => 30,
            'unit_cost' => 2500,
        ]);

        $movement = StockMovement::query()->where('movement_type', 'purchase_return')->first();
        $this->assertNotNull($movement);
    }

    public function test_purchase_return_can_be_deleted_and_restore_stock(): void
    {
        $user = User::factory()->create();
        [$invoice, $invoiceItem, $stockBatch] = $this->seedReturnableInvoice($user);

        $this
            ->actingAs($user)
            ->post(route('pembelian.retur-pembelian.store'), [
                'purchase_invoice_id' => $invoice->id,
                'return_date' => now()->toDateString(),
                'notes' => 'Kemasan penyok',
                'items' => [
                    [
                        'purchase_invoice_item_id' => $invoiceItem->id,
                        'quantity' => 10,
                        'reason' => 'Kemasan penyok',
                    ],
                ],
            ])
            ->assertRedirect(route('pembelian.retur-pembelian', ['purchase_invoice_id' => $invoice->id]));

        $purchaseReturn = PurchaseReturn::query()->first();
        $this->assertNotNull($purchaseReturn);

        $response = $this
            ->actingAs($user)
            ->delete(route('pembelian.retur-pembelian.destroy', $purchaseReturn));

        $response
            ->assertRedirect(route('pembelian.riwayat-retur-pembelian', ['purchase_invoice_id' => $invoice->id]))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success'
                && str_contains((string) ($toast['message'] ?? ''), 'stok dikembalikan'));

        $this->assertDatabaseMissing('purchase_returns', [
            'id' => $purchaseReturn->id,
        ]);

        $this->assertDatabaseMissing('purchase_return_items', [
            'purchase_return_id' => $purchaseReturn->id,
        ]);

        $this->assertDatabaseHas('stock_batches', [
            'id' => $stockBatch->id,
            'quantity_out' => 0,
            'quantity_balance' => 40,
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'purchase_return',
            'reference_table' => 'purchase_return_items',
        ]);
    }

    public function test_exchange_submission_uses_same_logic_and_redirects_to_exchange_page(): void
    {
        $user = User::factory()->create();
        [$invoice, $invoiceItem, $stockBatch] = $this->seedReturnableInvoice($user);

        $response = $this
            ->actingAs($user)
            ->post(route('pembelian.tukar-barang.store'), [
                'purchase_invoice_id' => $invoice->id,
                'return_date' => now()->toDateString(),
                'notes' => 'Barang ditukar',
                'items' => [
                    [
                        'purchase_invoice_item_id' => $invoiceItem->id,
                        'quantity' => 5,
                        'reason' => 'Barang ditukar',
                    ],
                ],
            ]);

        $purchaseReturn = PurchaseReturn::query()->first();

        $response
            ->assertRedirect(route('pembelian.tukar-barang', ['purchase_invoice_id' => $invoice->id]))
            ->assertSessionHas('toast', fn ($toast): bool => is_array($toast)
                && ($toast['type'] ?? null) === 'success'
                && str_contains((string) ($toast['message'] ?? ''), 'Tukar barang'));

        $this->assertNotNull($purchaseReturn);

        $this->assertDatabaseHas('purchase_return_items', [
            'purchase_return_id' => $purchaseReturn->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'medicine_id' => $stockBatch->medicine_id,
            'quantity' => 5,
        ]);
    }

    /**
     * Seed a purchase invoice with stock that can still be returned.
     *
     * @return array{0: PurchaseInvoice, 1: PurchaseInvoiceItem, 2: StockBatch}
     */
    private function seedReturnableInvoice(User $user): array
    {
        $principal = Principal::query()->create([
            'code' => 'PRN-RET-001',
            'name' => 'Kalbe Farma',
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'SUP-RET-001',
            'name' => 'PT Supplier Retur',
            'is_active' => true,
        ]);

        $medicine = Medicine::query()->create([
            'code' => 'OBT-RET-001',
            'name' => 'Paracetamol 500 mg',
            'principal_id' => $principal->id,
            'small_unit' => 'Tablet',
            'large_unit' => 'Box',
            'small_unit_per_large_unit' => 10,
            'is_active' => true,
        ]);

        $invoice = PurchaseInvoice::query()->create([
            'invoice_number' => 'INV-RET-001',
            'supplier_id' => $supplier->id,
            'invoice_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'status' => 'posted',
            'payment_status' => 'unpaid',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'tax_percentage' => 11,
            'tax_amount' => 11000,
            'grand_total' => 111000,
            'outstanding_amount' => 111000,
            'created_by' => $user->id,
        ]);

        $invoiceItem = PurchaseInvoiceItem::query()->create([
            'purchase_invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'purchase_unit' => 'Tablet',
            'unit_content' => 1,
            'batch_number' => 'BATCH-RET-01',
            'expiry_date' => now()->addMonths(8)->toDateString(),
            'quantity' => 40,
            'unit_price' => 2500,
            'discount_percentage' => 0,
            'discount_amount' => 0,
            'tax_amount' => 11000,
            'line_total' => 100000,
        ]);

        $stockBatch = StockBatch::query()->create([
            'medicine_id' => $medicine->id,
            'purchase_invoice_item_id' => $invoiceItem->id,
            'batch_number' => 'BATCH-RET-01',
            'expiry_date' => now()->addMonths(8)->toDateString(),
            'received_at' => now()->toDateString(),
            'purchase_price' => 2500,
            'initial_quantity' => 40,
            'quantity_in' => 40,
            'quantity_out' => 0,
            'quantity_balance' => 40,
            'status' => 'active',
        ]);

        return [$invoice, $invoiceItem, $stockBatch];
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
